@extends('layouts.app')

@section('title', 'Sửa group')

@section('content')
<h1 class="h4 mb-3">Sửa group <code>{{ $group->facebook_group_id }}</code></h1>
<form method="POST" action="{{ route('groups.update', $group) }}" class="card card-body" style="max-width: 640px">
    @method('PUT')
    @include('groups._form')
</form>
@endsection
