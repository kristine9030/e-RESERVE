<?php /** layouts/app_footer.php — closes the dashboard shell. */ ?>
        </main>

        <footer class="px-4 py-3 no-print d-flex flex-wrap justify-content-between gap-2"
                style="border-top:1px solid var(--line);font-size:.78rem;color:var(--ink-muted);">
            <span>&copy; <?= date('Y') ?> Supreme Student Council · <?= EVENT_HOST ?></span>
            <span><?= APP_NAME ?> <?= APP_VERSION ?> — <span class="text-gold fw-800"><?= APP_TAGLINE ?></span></span>
        </footer>
    </div>
</div>

<?php require __DIR__ . '/scripts.php'; ?>
