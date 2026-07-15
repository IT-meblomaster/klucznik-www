</main>

<footer class="footer">
    <div class="container-fluid">
        &copy; <?= date('Y') ?> <?= e($config['app']['name'] ?? 'Klucznik') ?>
    </div>
</footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script src="<?= e(
    ($config['app']['base_url'] ?? '')
    . '/assets/js/menu.js'
) ?>"></script>

<script src="<?= e(
    ($config['app']['base_url'] ?? '')
    . '/assets/js/app.js'
) ?>"></script>

<?php if (current_page() === 'key_inventory'): ?>
    <script src="<?= e(
        ($config['app']['base_url'] ?? '')
        . '/assets/js/key-inventory.js'
    ) ?>"></script>
<?php endif; ?>

<?php if (
    current_page() === 'keys'
    || current_page() === 'buildings'
): ?>
    <script src="<?= e(
        ($config['app']['base_url'] ?? '')
        . '/assets/js/keys.js'
    ) ?>"></script>
<?php endif; ?>

<?php if (current_page() === 'key_logs'): ?>
    <script src="<?= e(
        ($config['app']['base_url'] ?? '')
        . '/assets/js/key-logs.js'
    ) ?>"></script>
<?php endif; ?>
</body>
</html>