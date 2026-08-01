<?php

namespace App\CMS\Actions\Resolution;

use App\CMS\Actions\Contracts\ActionResolver;
use App\CMS\Actions\Contracts\ActionTargetResolver;
use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Data\ActionTargetDefinition;
use App\CMS\Actions\Data\ResolutionContext;
use App\CMS\Actions\Data\ResolvedAction;
use App\CMS\Actions\Enums\ActionResolutionReason;
use App\CMS\Actions\Enums\ActionResolutionStatus;
use App\CMS\Actions\Registry\ActionTargetRegistry;
use Illuminate\Contracts\Container\Container;
use Psr\Log\LoggerInterface;
use Throwable;

final class RuntimeActionResolver implements ActionResolver
{
    public function __construct(
        private readonly ActionTargetRegistry $registry,
        private readonly Container $container,
        private readonly LoggerInterface $logger,
    ) {}

    public function resolve(
        ActionDestination $destination,
        ResolutionContext $context,
    ): ResolvedAction {
        if ($destination->schemaVersion !== ActionDestination::SCHEMA_VERSION) {
            return ResolvedAction::invalid(
                $destination->type,
                ActionResolutionReason::UnsupportedSchemaVersion->value,
            );
        }

        $type = $destination->type;

        if ($type === null || ! ActionTargetDefinition::isValidKey($type)) {
            return ResolvedAction::invalid(
                $type,
                ActionResolutionReason::InvalidDestination->value,
            );
        }

        $definition = $this->registry->get($type);

        if (! $definition instanceof ActionTargetDefinition) {
            return ResolvedAction::invalid(
                $type,
                ActionResolutionReason::UnsupportedActionType->value,
            );
        }

        if ($definition->referenceBased) {
            if ($destination->referenceId === null) {
                return ResolvedAction::invalid(
                    $type,
                    ActionResolutionReason::MissingReferenceId->value,
                );
            }

            if ($destination->referenceId <= 0) {
                return ResolvedAction::invalid(
                    $type,
                    ActionResolutionReason::InvalidReferenceId->value,
                );
            }
        }

        try {
            $resolver = $this->container->make($definition->resolver);

            if (! $resolver instanceof ActionTargetResolver) {
                return $this->resolutionFailure($destination);
            }

            $result = $resolver->resolve($destination, $context);

            return $this->isSafeResult($result, $definition)
                ? $result
                : $this->resolutionFailure($destination);
        } catch (Throwable $exception) {
            $this->logger->error('Registered action target resolution failed.', [
                'action_type' => $type,
                'reference_id' => $destination->referenceId,
                'exception' => $exception,
            ]);

            return $this->resolutionFailure($destination);
        }
    }

    public function resolveMany(
        iterable $destinations,
        ResolutionContext $context,
    ): array {
        $results = [];

        foreach ($destinations as $destination) {
            $results[] = $destination instanceof ActionDestination
                ? $this->resolve($destination, $context)
                : ResolvedAction::invalid(
                    null,
                    ActionResolutionReason::InvalidDestination->value,
                );
        }

        return $results;
    }

    private function isSafeResult(
        ResolvedAction $result,
        ActionTargetDefinition $definition,
    ): bool {
        if ($result->actionType !== $definition->key) {
            return false;
        }

        if ($result->status !== ActionResolutionStatus::Resolved) {
            return true;
        }

        return $this->isSafeUrl($result->url, $definition->key)
            && $this->hasSafeMetadata($result->metadata);
    }

    private function isSafeUrl(?string $url, string $actionType): bool
    {
        if ($url === null || $url === '' || trim($url) !== $url) {
            return false;
        }

        if (preg_match('/[\x00-\x20\x7F]/', $url) === 1
            || str_contains($url, '\\')
            || str_starts_with($url, '//')) {
            return false;
        }

        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $url, $matches) === 1) {
            $scheme = strtolower($matches[1]);

            if ($scheme === 'mailto' && $actionType === 'email') {
                return filter_var(substr($url, 7), FILTER_VALIDATE_EMAIL) !== false;
            }

            if ($scheme === 'tel' && $actionType === 'phone') {
                return preg_match('/^\+?[0-9]{3,20}$/', substr($url, 4)) === 1;
            }

            return in_array($scheme, ['http', 'https'], true)
                && filter_var($url, FILTER_VALIDATE_URL) !== false;
        }

        if ($url === '#') {
            return $actionType === 'custom_url';
        }

        if (str_starts_with($url, '#')) {
            return preg_match('/^#[A-Za-z][A-Za-z0-9_.:-]*$/', $url) === 1;
        }

        return str_starts_with($url, '/')
            || str_starts_with($url, '?')
            || preg_match('/^[^\s?#][^\s#]*$/u', $url) === 1;
    }

    /** @param array<string, mixed> $metadata */
    private function hasSafeMetadata(array $metadata): bool
    {
        foreach ($metadata as $key => $value) {
            if (preg_match('/^[a-z][a-z0-9_]*$/', $key) !== 1
                || (! is_scalar($value) && $value !== null)) {
                return false;
            }

            if (str_ends_with($key, '_url')
                && $value !== null
                && (! is_string($value) || ! $this->isSafeUrl($value, 'custom_url'))) {
                return false;
            }
        }

        return true;
    }

    private function resolutionFailure(ActionDestination $destination): ResolvedAction
    {
        return ResolvedAction::unavailable(
            (string) $destination->type,
            ActionResolutionReason::UrlResolutionFailed->value,
            ['reference_id' => $destination->referenceId],
        );
    }
}
