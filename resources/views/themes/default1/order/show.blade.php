@extends('themes.default1.layouts.master')
@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Orders</h3>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="callout callout-info">
                            <div class="row">
                                <div class="col-md-3">
                                    <b>Date: </b>{{$order->created_at}} 
                                </div>
                                <div class="col-md-3">
                                    <b>Invoice No: </b> #{{$invoice->number}}
                                </div>
                                <div class="col-md-3">
                                    <b>Order No: </b>  #{{$order->number}} 

                                </div>
                                <div class="col-md-3">
                                    <b>Status: </b>{{$order->order_status}}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">

                            <table class="table table-hover">
                                <tbody><tr><td><b>Name:</b></td><td><a href="{{url('clients/'.$user->id)}}">{{ucfirst($user->first_name)}}</a></td></tr>
                                    <tr><td><b>Email:</b></td><td>{{$user->email}}</td></tr>
                                    <tr><td><b>Mobile:</b></td><td>{{$user->mobile}}</td></tr>
                                    <tr><td><b>Address:</b></td><td>{{$user->address}}, 
                                            {{ucfirst($user->town)}}, 
                                            @if(key_exists('name',\App\Http\Controllers\Front\CartController::getStateByCode($user->state)))
                                            {{\App\Http\Controllers\Front\CartController::getStateByCode($user->state)['name']}}
                                            @endif
                                        </td></tr>
                                    <tr><td><b>Country:</b></td><td>{{\App\Http\Controllers\Front\CartController::getCountryByCode($user->country)}}</td></tr>

                                </tbody></table>
                        </div>
                        <div class="col-md-6">


                            <table class="table table-hover">
                                <tbody><tr><td><b>Serial Key:</b></td><td>{{$order->serial_key}}</td></tr>
                                    <tr><td><b>Domain Name:</b></td><td>{{$order->domain}}</td></tr>
                                    <?php
                                    if ($subscription->end_at == '' || $subscription->end_at == '0000-00-00 00:00:00') {
                                        $sub = "--";
                                    } else {
                                        $sub = $subscription->end_at;
                                    }
                                    ?>
                                    <tr><td><b>Subscription End:</b></td><td>{{$sub}}</td></tr>

                                </tbody></table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Transcatin list</h3>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-md-12">
                        {!! Datatable::table()
                        ->addColumn('Number','Products','Date','Total','Action')
                        ->setUrl('../get-my-invoices/'.$order->id.'/'.$user->id) 
                        ->setOptions([
                        "order"=> [ 1, "desc" ],
                        ])
                        ->render() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Payment receipts</h3>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-md-12">
                        {!! Datatable::table()
                        ->addColumn('Invoice Number','Total','Method','Status')
                        ->setUrl('../get-my-payment/'.$order->id.'/'.$user->id) 
                        ->setOptions([
                        "order"=> [ 1, "desc" ],
                        ])
                        ->render() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@stop