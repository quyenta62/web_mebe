@extends('layouts.app')

@section('title', 'Đăng nhập')

@section('content')
<div class="row justify-content-center mt-5">
    <div class="col-sm-8 col-md-5 col-lg-4">
        <h1 class="h4 mb-3">Đăng nhập</h1>

        @unless ($configured)
            <div class="alert alert-warning">
                Chưa cấu hình tài khoản. Điền <code>ADMIN_USERNAME</code> và <code>ADMIN_PASSWORD</code> trong file <code>.env</code>.
            </div>
        @endunless

        <form method="POST" action="{{ route('login.attempt') }}" class="card card-body">
            @csrf
            <div class="mb-3">
                <label for="username" class="form-label">Tài khoản</label>
                <input id="username" name="username" type="text" value="{{ old('username') }}" required autofocus
                       autocomplete="username" class="form-control @error('username') is-invalid @enderror">
                @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Mật khẩu</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                       class="form-control @error('password') is-invalid @enderror">
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary">Đăng nhập</button>
        </form>
    </div>
</div>
@endsection
