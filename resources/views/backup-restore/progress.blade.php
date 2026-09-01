<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="referrer" content="no-referrer">
    <title>در حال بازیابی نسخه پشتیبان</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f3f6fb;color:#172033;font-family:Tahoma,Arial,sans-serif;padding:24px}.card{width:min(620px,100%);background:#fff;border:1px solid #dbe3ef;border-radius:18px;padding:36px;box-shadow:0 18px 50px rgba(24,42,73,.1);text-align:center}.spinner{width:52px;height:52px;border:5px solid #dce8ff;border-top-color:#2563eb;border-radius:50%;margin:0 auto 24px;animation:spin 1s linear infinite}.is-terminal .spinner{display:none}h1{font-size:24px;margin:0 0 16px}.status{font-size:17px;font-weight:700;color:#245dd8;margin:0 0 18px}.help,.failure-help{line-height:2;color:#5f6b7d;margin:0}.failure{color:#b42318}.success{color:#15803d}@keyframes spin{to{transform:rotate(360deg)}}
    </style>
</head>
<body>
<main class="card" data-restore-progress data-status-url="{{ $statusUrl }}">
    <div class="spinner" aria-hidden="true"></div>
    <h1>در حال بازیابی نسخه پشتیبان</h1>
    <p class="status" aria-live="polite">{{ $restore->status->label() }}</p>
    <p class="help">لطفاً این صفحه را نبندید. پس از پایان عملیات، به‌صورت خودکار به وب‌سایت منتقل خواهید شد.</p>
    <p class="failure-help" hidden>نسخه پشتیبان ایمنی حفظ شده است و مدیر سیستم می‌تواند عملیات بازیابی را ادامه دهد.</p>
</main>
<script>
(() => {
    const root = document.querySelector('[data-restore-progress]')
    const status = root.querySelector('.status')
    const help = root.querySelector('.help')
    const failureHelp = root.querySelector('.failure-help')
    let stopped = false
    const poll = async () => {
        if (stopped) return
        try {
            const response = await fetch(root.dataset.statusUrl, {headers: {'Accept': 'application/json'}, cache: 'no-store'})
            if (!response.ok) throw new Error('status unavailable')
            const state = await response.json()
            status.textContent = state.label
            if (state.completed) {
                stopped = true
                root.classList.add('is-terminal')
                status.classList.add('success')
                status.textContent = 'بازیابی نسخه پشتیبان با موفقیت انجام شد.'
                help.textContent = 'در حال انتقال به صفحه اصلی وب‌سایت…'
                setTimeout(() => window.location.replace('/'), 1500)
                return
            }
            if (state.failed) {
                stopped = true
                root.classList.add('is-terminal')
                status.classList.add('failure')
                status.textContent = 'بازیابی نسخه پشتیبان با خطا مواجه شد. ' + (state.failure_message || '')
                help.textContent = 'وب‌سایت برای جلوگیری از نمایش اطلاعات ناقص در حالت نگهداری باقی مانده است.'
                failureHelp.hidden = false
                return
            }
        } catch (_) {
            status.textContent = 'در حال دریافت آخرین وضعیت بازیابی…'
        }
        setTimeout(poll, 2500)
    }
    setTimeout(poll, 800)
})()
</script>
</body>
</html>
