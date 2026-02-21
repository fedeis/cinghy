<div class="flex-row align-center justify-between mb-lg">
    <h1>Journal Files</h1>
    <div class="flex-row gap-sm">
        <a href="/files/new" class="btn btn-primary">+ New Journal</a>
    </div>
</div>

<div class="card p-0">
    <table class="table files">
        <thead>
            <tr>
                <th>Name</th>
                <th>Size</th>
                <th>Modified</th>
                <th class="text-right">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($files as $file): ?>
                <tr>
                    <td>
                        <div class="flex-row align-center gap-sm">
                            <strong><a href="/files/edit?file=<?php echo urlencode($file['name']); ?>" title="Edit"><?php echo htmlspecialchars($file['name']); ?></a></strong>
                        </div>
                    </td>
                    <td class="text-muted text-sm"><?php echo number_format($file['size'] / 1024, 1); ?> KB</td>
                    <td class="text-muted text-sm"><?php echo date('d/m/y H:i', $file['modified']); ?></td>
                    <td class="text-right">
                        <div class="flex-row justify-end gap-xs">
                            <!-- <a href="/files/edit?file=<?php echo urlencode($file['name']); ?>" class="btn-icon" title="Edit">✏️</a> -->
                            <form action="/files/delete" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this file? This cannot be undone.')">
                                <input type="hidden" name="filename" value="<?php echo htmlspecialchars($file['name']); ?>">
                                <button type="submit" class="btn-icon" title="Delete">🗑️</button>
                                <input type="hidden" name="csrf_token" value="<?= \App\Core\Router::csrfToken() ?>">
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($files)): ?>
                <tr>
                    <td colspan="4" class="text-center p-lg text-muted">No journal files found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
/* Local overrides for the file table if needed, though most should be covered by app.css */
.text-right { text-align: right; }
.justify-end { justify-content: flex-end; }
</style>
