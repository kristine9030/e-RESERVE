<?php /** layouts/app_footer.php — closes the dashboard shell. */ ?>
        </div>
    </main>

    <footer class="app-foot no-print">
        <div class="app-container d-flex flex-wrap justify-content-between gap-2">
            <span>&copy; <?= date('Y') ?> Supreme Student Council · <?= EVENT_HOST ?></span>
            <span><?= APP_NAME ?> <?= APP_VERSION ?> — <span class="text-gold fw-800"><?= APP_TAGLINE ?></span></span>
        </div>
    </footer>
</div>

<?php require __DIR__ . '/scripts.php'; ?>
