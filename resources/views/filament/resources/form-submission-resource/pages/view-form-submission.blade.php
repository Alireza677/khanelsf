<x-filament-panels::page>
<div class=submission-view dir=rtl>
<section class=submission-card>
<header><h2>خلاصه ورودی</h2></header>
<div class=summary-grid>
<div><span>فرم</span><strong>{{ $submission->form?->name ?? '—' }}</strong></div>
<div><span>وضعیت</span><strong class=badge>{{ $statusLabel }}</strong></div>
<div><span>منبع</span><strong>{{ $sourceLabel }}</strong></div>
<div><span>تاریخ ارسال</span><strong>{{ \App\Support\PersianDate::dateTime($submission->submitted_at) ?? '—' }}</strong></div>
<div><span>برگه / صفحه</span><strong>{{ $submission->page?->title ?? '—' }}</strong></div>
<div><span>نشانی صفحه</span><strong class=ltr>{{ $submission->page_url ?: '—' }}</strong></div>
</div>
</section>
@if ($files !== [])
<section class=submission-card>
<header><h2>فایل‌ها <small>{{ count($files) }} فایل</small></h2></header>
<div class=files-grid>@foreach ($files as $file) @include('filament.resources.form-submission-resource.pages._file') @endforeach</div>
</section>
@endif
<section class=submission-card><header><h2>اطلاعات ارسال‌کننده</h2></header><div class=sender-grid>
<div><span>نام</span><strong>{{ $submitter->name }}</strong></div>
<div><span>ایمیل</span><a href=mailto:{{ $submitter->email }}>{{ $submitter->email ?? '—' }}</a></div>
<div><span>تلفن</span><a href=tel:{{ $submitter->phone }}>{{ $submitter->phone ?? '—' }}</a></div>
</div></section>
@if ($submission->lead)
<section class=related-lead><span>سرنخ مرتبط</span><strong>{{ $submission->lead->name ?? 'سرنخ مرتبط' }}</strong>
<a href={{ \App\Filament\Resources\LeadResource::getUrl('view', ['record' => $submission->lead]) }}>مشاهده سرنخ</a></section>
@endif
@if ($answerGroups !== [])
<section class=submission-card><header><h2>پاسخ‌ها</h2></header><div class=answer-groups>
@foreach ($answerGroups as $group)
<details class=answer-group @if($loop->first) open @endif><summary>{{ $group->title }} — {{ $group->count }} مورد</summary>
<div class=answers-grid>
@foreach ($group->answers as $answer)
<div class=answer-item><span>{{ $answer->label }}</span>
@if ($answer->attachments === [])<strong>{{ $answer->value }}</strong>@endif
@foreach ($answer->attachments as $file) @include('filament.resources.form-submission-resource.pages._file') @endforeach
</div>
@endforeach
</div></details>
@endforeach
</div></section>
@endif
@if ($calculation->result || $calculation->scores !== [])
<details class=submission-card><summary>نتیجه محاسبه</summary>
<p>{{ $calculation->result ?: 'هیچ گزینه واجد شرایطی یافت نشد؛ بررسی کارشناسی لازم است.' }}</p>
@foreach ($calculation->scores as $score)
<p>@if ($score['rank'] !== null)رتبه {{ $score['rank'] }} — @endif{{ $score['label'] }}: {{ $score['value'] }} @if ($score['eligible'] === false)— خارج از شرایط @endif</p>
@if ($score['reason_text'] !== '')<p>علت: {{ $score['reason_text'] }}</p>@endif
@endforeach
</details>
@endif
<details class=submission-card><summary>اطلاعات فنی</summary>
<p>شناسه داخلی ورودی: {{ $submission->getKey() }}</p><p>شناسه داخلی بلوک: {{ $submission->block_id ?? '—' }}</p>
@foreach ($technicalFields as $key => $value)<p>{{ $key }}: {{ $value }}</p>@endforeach
</details>
<div class=submission-actions><a href={{ \App\Filament\Resources\FormSubmissionResource::getUrl('index') }}>بازگشت به لیست ورودی‌ها</a>
<button type=button onclick=window.print()>چاپ</button></div>
</div>
</x-filament-panels::page>
@push('styles')
<style>
.submission-view{display:grid;gap:1rem}.submission-card{background:rgb(var(--gray-50));border:1px solid rgb(var(--gray-200));border-radius:.75rem;box-shadow:0 1px 3px #0000000d;overflow:hidden}.dark .submission-card{background:rgb(var(--gray-900));border-color:rgb(var(--gray-700))}.submission-card>header{display:flex;align-items:center;padding:.85rem 1rem;border-bottom:1px solid rgb(var(--gray-200))}.submission-card h2{font-size:.95rem;font-weight:700}.submission-card small,.summary-grid span,.sender-grid span,.answer-item>span{font-size:.72rem;color:rgb(var(--gray-500))}.summary-grid,.sender-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));padding:1rem}.summary-grid>div,.sender-grid>div{display:grid;gap:.3rem;padding:.15rem 1rem;min-width:0}.summary-grid strong,.sender-grid a{font-size:.84rem;overflow-wrap:anywhere}.ltr{direction:ltr;text-align:right}.badge{width:max-content;padding:.2rem .55rem;border-radius:999px;background:rgb(var(--primary-50));color:rgb(var(--primary-700))}.files-grid,.answers-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem;padding:1rem}.file-card{display:flex;align-items:center;gap:.65rem;border:1px solid rgb(var(--gray-200));border-radius:.6rem;padding:.6rem;min-width:0}.file-icon{width:2.75rem;height:2.75rem;display:grid;place-items:center;background:rgb(var(--gray-100));border-radius:.45rem;flex:none}.file-icon svg,.file-download svg{width:1.2rem}.file-info{display:grid;min-width:0;flex:1}.file-info strong{font-size:.78rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.file-info span,.submission-card>p{font-size:.7rem;color:rgb(var(--gray-500))}.file-download{padding:.4rem}.related-lead,.submission-actions{display:flex;justify-content:space-between;padding:.8rem 1rem}.answer-groups{display:grid;gap:.6rem;padding:.75rem}.answer-group{border:1px solid rgb(var(--gray-200));border-radius:.6rem}.answer-group summary,.submission-card>summary{cursor:pointer;padding:.75rem 1rem;font-weight:600}.answer-item{display:grid;gap:.3rem;padding:.5rem;min-width:0}.answer-item strong{font-size:.84rem;white-space:pre-wrap;overflow-wrap:anywhere}.submission-actions>*{padding:.55rem .8rem;border:1px solid rgb(var(--gray-200));border-radius:.5rem;color:rgb(var(--primary-600));font-size:.8rem}@media(max-width:1024px){.files-grid,.answers-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:640px){.summary-grid,.sender-grid,.files-grid,.answers-grid{grid-template-columns:1fr}.summary-grid>div,.sender-grid>div{padding:.55rem}.submission-actions{gap:.5rem;flex-wrap:wrap}}@media print{.fi-sidebar,.fi-topbar,.fi-header-actions,.submission-actions{display:none!important}.answer-group:not([open])>*:not(summary){display:block}}
</style>
@endpush
