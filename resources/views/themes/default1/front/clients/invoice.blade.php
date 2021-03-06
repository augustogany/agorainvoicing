@extends('themes.default1.layouts.front.myaccount_master')
@section('title')
Agora | Invoice
@stop
@section('nav-invoice')
active
@stop
@section('breadcrumb')
<li><a href="{{url('home')}}">Home</a></li>
<li class="active">Invoices</li>
@stop

@section('content')

<h2 class="mb-none"> My Invoices</h2>

<div class="col-md-12 pull-center">

	 <table id="invoice-table" class="table display" cellspacing="0" width="100%" styleClass="borderless">
	 	
                    <thead><tr>
                            <th>Number</th>
                            <th>Date</th>
                            <th>Total</th>
                           
                            <th>Action</th>
                        </tr></thead>


                </table>

            </div>
            <link rel="stylesheet" type="text/css" href="//cdn.datatables.net/1.10.12/css/jquery.dataTables.min.css" />
<script src="//cdn.datatables.net/1.10.12/js/jquery.dataTables.min.js"></script>
<script type="text/javascript">
        $('#invoice-table').DataTable({
            processing: true,
            serverSide: true,
            order: [[ 0, "desc" ]],
            ajax: '{!! route('get-my-invoices') !!}',
            "oLanguage": {
                "sLengthMenu": "_MENU_ Records per page",
                "sSearch"    : "Search: ",
                "sProcessing": '<img id="blur-bg" class="backgroundfadein" style="top:40%;left:50%; width: 50px; height:50 px; display: block; position:    fixed;" src="{!! asset("lb-faveo/media/images/gifloader3.gif") !!}">'
            },
    
            columns: [
                {data: 'number', name: 'number'},
                {data: 'date', name: 'date'},
                {data: 'total', name: 'total'},
                
                {data: 'Action', name: 'Action'}
            ],
            "fnDrawCallback": function( oSettings ) {
                $('.loader').css('display', 'none');
            },
            "fnPreDrawCallback": function(oSettings, json) {
                $('.loader').css('display', 'block');
            },
        });
    </script>
 

   


@stop