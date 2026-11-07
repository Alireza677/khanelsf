@extends('layouts.account')

@section('account-content')
    @include('shop.partials.order-detail', ['isReceipt' => false, 'inAccount' => true])
@endsection
