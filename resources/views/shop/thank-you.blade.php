@extends($inAccount ? 'layouts.account' : 'layouts.app')

@section($inAccount ? 'account-content' : 'content')
    @include('shop.partials.order-detail', ['isReceipt' => true])
@endsection
