@extends('layouts.app')

@section('title', 'Thêm group')

@section('content')
<h1 class="h4 mb-3">Thêm group</h1>
<form method="POST" action="{{ route('groups.store') }}" class="card card-body" style="max-width: 640px">
    @include('groups._form')
</form>
@endsection
