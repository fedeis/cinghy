<?php if (isset($error)): ?>
    <div class="error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<form action="/login" method="post">
    <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autofocus>
    </div>
    <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
    </div>
    <div class="form-group">
        <label>
            <input type="checkbox" name="remember" value="1">
            Ricordami (90 giorni)
        </label>
    </div>
    <button type="submit" class="btn">Log In</button>
    <input type="hidden" name="csrf_token" value="<?= \App\Core\Router::csrfToken() ?>">
</form>
