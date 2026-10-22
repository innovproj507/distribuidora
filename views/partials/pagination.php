<?php use App\Core\View; ?>
<!-- Pagination Component -->
<?php
$totalPages = $pagination['total_pages'] ?? 0;
$currentPage = $pagination['current_page'] ?? 1;
$total = $pagination['total'] ?? 0;
$hasPrevious = $pagination['has_previous'] ?? false;
$hasNext = $pagination['has_next'] ?? false;
$previousPage = $pagination['previous_page'] ?? 1;
$nextPage = $pagination['next_page'] ?? 1;
?>
<?php if ($totalPages > 1): ?>
<div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 rounded-b-xl mt-0">
    <div class="flex-1 flex items-center justify-between">
        <p class="text-xs text-gray-700">
            Página <span class="font-medium"><?= $currentPage ?></span>
            de <span class="font-medium"><?= $totalPages ?></span>
            (<span class="font-medium"><?= number_format($total) ?></span> registros)
        </p>
        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
            <?php if ($hasPrevious): ?>
            <?php
            $prevParams = $_GET;
            $prevParams['page'] = $previousPage;
            ?>
            <a href="?<?= http_build_query($prevParams) ?>"
               class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-xs font-medium text-gray-500 hover:bg-gray-50">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>

            <?php
            $start = max(1, $currentPage - 2);
            $end = min($totalPages, $currentPage + 2);
            for ($i = $start; $i <= $end; $i++):
                $pageParams = $_GET;
                $pageParams['page'] = $i;
            ?>
                <?php if ($i === $currentPage): ?>
                <span class="relative inline-flex items-center px-4 py-2 border border-sky-500 bg-sky-500 text-xs font-medium text-white">
                    <?= $i ?>
                </span>
                <?php else: ?>
                <a href="?<?= http_build_query($pageParams) ?>"
                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50">
                    <?= $i ?>
                </a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($hasNext): ?>
            <?php
            $nextParams = $_GET;
            $nextParams['page'] = $nextPage;
            ?>
            <a href="?<?= http_build_query($nextParams) ?>"
               class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-xs font-medium text-gray-500 hover:bg-gray-50">
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </nav>
    </div>
</div>
<?php endif; ?>
