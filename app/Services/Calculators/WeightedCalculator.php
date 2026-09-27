<?php

namespace App\Services\Calculators;

use App\Models\Form;
use App\Services\FormSchema;
use Illuminate\Validation\ValidationException;

final class WeightedCalculator
{
    private const CHOICE_TYPES = ['select', 'radio', 'radio_card', 'image_choice', 'checkbox'];

    public function __construct(
        private readonly CalculatorScoringSchema $contract,
        private readonly FormSchema $fields,
        private readonly CalculatorEligibilityEvaluator $eligibilityEvaluator,
        private readonly DecisionReportBuilder $decisionReports,
    ) {}

    public function configuration(Form $form): array
    {
        $schema = $form->schema ?? [];
        if (! is_array($schema) || ! is_array($schema['fields'] ?? [])) {
            throw InvalidCalculatorConfiguration::forForm($form, 'ساختار فیلدهای فرم معتبر نیست.');
        }
        $recommendations = data_get($schema, 'calculator.recommendations');
        if (! is_array($recommendations) || $recommendations === []) {
            throw InvalidCalculatorConfiguration::forForm($form, 'حداقل یک نتیجه باید تعریف شود.');
        }
        foreach ($recommendations as $key => $label) {
            if (! is_string($key) || preg_match('/^[a-z][a-z0-9_]*$/D', $key) !== 1 || ! is_string($label) || trim($label) === '') {
                throw InvalidCalculatorConfiguration::forForm($form, 'شناسه یا عنوان نتیجه معتبر نیست.');
            }
        }
        // Runtime must not generate criterion identities that could not match saved mappings.
        foreach (is_array(data_get($schema, 'calculator.criteria')) ? $schema['calculator']['criteria'] : [] as $criterion) {
            if (! is_array($criterion) || ! isset($criterion['id'])) {
                throw InvalidCalculatorConfiguration::forForm($form, 'شناسهٔ معیار ذخیره نشده است؛ فرم را در ویرایشگر اصلاح و ذخیره کنید.');
            }
        }
        try {
            return $this->contract->normalize($schema);
        } catch (ValidationException $exception) {
            throw InvalidCalculatorConfiguration::forForm($form, $exception->validator->errors()->first());
        }
    }

    public function calculate(Form $form, array $payload): CalculationResult
    {
        $schema = $this->configuration($form);
        $runtimeForm = clone $form;
        $runtimeForm->schema = $schema;
        $calculator = $schema['calculator'];
        $criteria = $calculator['criteria'];
        $recommendations = array_map('trim', $calculator['recommendations']);
        $weights = [];
        $labels = [];
        foreach ($criteria as $criterion) {
            $weights[$criterion['id']] = CalculatorDecimal::value($criterion['base_weight']);
            $labels[$criterion['id']] = $criterion['label'];
        }
        $answers = [];
        $answerLabels = [];
        foreach ($this->fields->fields($runtimeForm, preserveChoiceOptions: true) as $field) {
            if (! in_array($field['type'], self::CHOICE_TYPES, true)) {
                continue;
            }
            $answer = $payload[$field['name']] ?? null;
            if (in_array($answer, [null, '', []], true) && ! $field['required']) {
                continue;
            }
            $selected = $field['type'] === 'checkbox' ? $answer : [$answer];
            if (! is_array($selected) || $selected === [] || count(array_filter($selected, 'is_string')) !== count($selected)
                || count(array_unique($selected, SORT_STRING)) !== count($selected)) {
                $this->invalidAnswer($field);
            }
            $selectedLabels = [];
            foreach ($selected as $value) {
                $option = collect($field['options'])->first(fn (array $option): bool => $option['value'] === $value);
                if (! is_array($option)) {
                    $this->invalidAnswer($field);
                }
                $selectedLabels[] = $option['label'];
                foreach ($option['criterion_weights'] ?? [] as $id => $weight) {
                    if (array_key_exists($id, $weights)) {
                        $weights[$id] = CalculatorDecimal::add($weights[$id], CalculatorDecimal::value($weight));
                    }
                }
            }
            $answers[$field['name']] = $field['type'] === 'checkbox' ? array_values($selected) : $answer;
            $answerLabels[$field['name']] = implode('، ', $selectedLabels);
        }

        $maximum = '0';
        foreach ($weights as $weight) {
            $maximum = CalculatorDecimal::add($maximum, CalculatorDecimal::multiply($weight, '5'));
        }
        $noScore = CalculatorDecimal::compare($maximum, '0') === 0;
        $scores = $percentages = $contributions = [];
        foreach ($recommendations as $key => $label) {
            $scores[$key] = '0';
            $contributions[$key] = [];
            foreach ($weights as $id => $weight) {
                $performance = CalculatorDecimal::value($calculator['criterion_scores'][$key][$id] ?? 0);
                $contribution = CalculatorDecimal::multiply($weight, $performance);
                $contributions[$key][$id] = $contribution;
                $scores[$key] = CalculatorDecimal::add($scores[$key], $contribution);
            }
            $percentages[$key] = $noScore ? null : CalculatorDecimal::percentage($scores[$key], $maximum);
        }

        // Eligibility uses exactly the existing evaluator and never changes computed scores.
        $eligibility = $this->eligibilityEvaluator->evaluate($form, $payload, $recommendations);
        $ranking = [];
        foreach ($recommendations as $key => $label) {
            $ranking[] = [
                'key' => $key, 'label' => $label, 'score' => $scores[$key], 'raw_score' => $scores[$key],
                'suitability_percentage' => $percentages[$key],
                'eligible' => $eligibility[$key]['eligible'], 'reasons' => $eligibility[$key]['reasons'],
                'order' => count($ranking),
            ];
        }
        usort($ranking, fn (array $a, array $b): int => ($b['eligible'] <=> $a['eligible'])
            ?: CalculatorDecimal::compare($b['score'], $a['score']) ?: ($a['order'] <=> $b['order']));
        $rank = 0;
        $recommended = null;
        foreach ($ranking as &$row) {
            $row['rank'] = ! $noScore && $row['eligible'] ? ++$rank : null;
            if ($row['rank'] === 1) {
                $recommended = $row['key'];
            }
            unset($row['order']);
        }
        unset($row);
        $factors = [];
        foreach ($recommended === null ? [] : $contributions[$recommended] as $id => $contribution) {
            if (CalculatorDecimal::compare($contribution, '0') > 0) {
                $factors[] = ['criterion_id' => $id, 'label' => $labels[$id], 'contribution' => $contribution, 'order' => count($factors)];
            }
        }
        usort($factors, fn (array $a, array $b): int => CalculatorDecimal::compare($b['contribution'], $a['contribution']) ?: ($a['order'] <=> $b['order']));
        $factors = array_map(static function (array $factor): array {
            unset($factor['order']);

            return $factor;
        }, array_slice($factors, 0, 3));

        $noEligibleRecommendation = ! in_array(true, array_column($eligibility, 'eligible'), true);
        $decisionReport = $this->decisionReports->build(
            scoringMode: CalculatorScoringSchema::WEIGHTED,
            config: $calculator['decision_report'] ?? [],
            resultKey: $recommended,
            resultLabel: $recommended === null ? null : $recommendations[$recommended],
            suitabilityPercentage: $recommended === null ? null : $percentages[$recommended],
            topFactors: $factors,
            noScore: $noScore,
            noEligibleRecommendation: $noEligibleRecommendation,
        );

        return new CalculationResult(
            calculatorIdentifier: $form->calculator_identifier ?: $form->slug,
            answers: $answers, answerLabels: $answerLabels,
            recommendedMethod: $recommended, result: $recommended === null ? null : $recommendations[$recommended],
            scores: $scores, ranking: $ranking, eligibility: $eligibility,
            noEligibleRecommendation: $noEligibleRecommendation,
            weightedDetails: [
                'scoring_mode' => CalculatorScoringSchema::WEIGHTED,
                'no_score' => $noScore, 'max_possible_score' => $maximum,
                'criterion_weights' => $weights, 'criterion_labels' => $labels,
                'criterion_contributions' => $contributions, 'suitability_percentages' => $percentages,
                'top_factors' => $factors,
                ...($decisionReport === null ? [] : ['decision_report' => $decisionReport]),
            ],
            resultContent: app(CalculatorResultContent::class)->resolve($schema, $recommended),
        );
    }

    private function invalidAnswer(array $field): never
    {
        throw ValidationException::withMessages([$field['name'] => 'پاسخ انتخاب‌شده برای «'.$field['label'].'» معتبر نیست.']);
    }
}
