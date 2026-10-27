@extends('layout')

@section('title', 'Masuk admin')

@section('content')
    <div class="admin-box">
        <h1>Masuk admin</h1>
        <p class="muted">Khusus pengelola parkir.</p>

        <form method="POST" action="{{ route('admin.login') }}" class="form">
            @csrf
            <label>Email
                <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            </label>
            <label>Kata sandi
                <input type="password" name="password" required autocomplete="current-password">
            </label>
            <label class="check"><input type="checkbox" name="remember" value="1"> Ingat saya</label>
            @error('email')
                <p class="error">{{ $message }}</p>
            @enderror
            <button type="submit">Masuk</button>
        </form>
    </div>
@endsection
