<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <title>Wallet</title>
</head>
<body>

<h2>Bank accounts</h2>

@if($bankAccounts->isEmpty())
    <p>No bank accounts</p>
@else
    @foreach($bankAccounts as $bank)
        <div style="border:1px solid #ccc; padding:12px; margin-bottom:10px;">
            <strong>{{ $bank->name }}</strong><br>
            Balance: 0.00 {{ $bank->currency }}<br>
            <small>Read-only</small>
        </div>
    @endforeach
@endif

</body>
</html>
