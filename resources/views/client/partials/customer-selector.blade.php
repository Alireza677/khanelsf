@if ($portalCustomers->count() > 1)
    <form method="GET" action="{{ $action }}" class="portal-field">
        <label for="customer">حساب مشتری</label>
        <select class="portal-select" id="customer" name="customer" onchange="this.form.submit()">
            @foreach ($portalCustomers as $option)
                <option value="{{ $option->id }}" @selected($portalCustomer?->is($option))>{{ $option->display_name }}</option>
            @endforeach
        </select>
    </form>
@endif
