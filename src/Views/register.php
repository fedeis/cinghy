<?php if (isset($error)): ?>
    <div class="error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<h2 style="font-size: 1.25rem; font-weight: 600; text-align: center; margin-bottom: 2rem;">
    Welcome! Create your superadmin account.
</h2>

<form action="/register" method="post">
    <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autofocus>
    </div>
    <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required>
        <small style="display:block; margin-top:0.25rem; color:#888;">For password recovery.</small>
    </div>
    <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
    </div>
    <div class="form-group">
        <label for="password_confirm">Confirm Password</label>
        <input type="password" id="password_confirm" name="password_confirm" required>
    </div>
    <button type="submit" class="btn">Complete Setup</button>
</form>
