    <div class="flex-row align-center gap-sm">
        <a href="/files" class="btn-icon" title="Back">←</a>
        <h2 class="mb-0">
            <?php if (empty($filename)): ?>
                New Journal
            <?php else: ?>
                Editing: <span class="text-accent"><?php echo htmlspecialchars($filename); ?></span>
            <?php endif; ?>
        </h2>
    </div>
    <div class="flex-row gap-sm mb-lg">
        <button type="submit" form="edit-form" class="btn btn-primary">Save Changes</button>
    </div>
</div>

<div class="card p-0 overflow-hidden">
    <form id="edit-form" action="/files/save" method="POST" class="flex-col" style="height: 70vh;">
        <?php if (empty($filename)): ?>
            <div class="p-md border-bottom bg-default">
                <div class="flex-row align-center gap-sm">
                    <label class="font-medium">Filename:</label>
                    <input type="text" name="filename" placeholder="e.g. 2026.journal" class="flex-1" required pattern="^.*\.journal$" title="Filename must end with .journal">
                </div>
            </div>
        <?php else: ?>
            <input type="hidden" name="filename" value="<?php echo htmlspecialchars($filename); ?>">
        <?php endif; ?>
        <textarea name="content" class="editor-textarea" spellcheck="false"><?php echo htmlspecialchars($content); ?></textarea>
        <input type="hidden" name="csrf_token" value="<?= \App\Core\Router::csrfToken() ?>">
    </form>
</div>

<style>
.editor-textarea {
    flex: 1;
    width: 100%;
    height: 100%;
    border: none;
    padding: var(--space-md);
    background: var(--bg-body);
    color: var(--text-color);
    font-family: var(--font-mono);
    font-size: 11px;
    line-height: 1.6;
    resize: none;
    outline: none;
}

/* Improve card container for the editor */
.overflow-hidden { overflow: hidden; }
</style>
