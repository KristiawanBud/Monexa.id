<?php

$old = <<<'OLD'
    private function resolveCategory(User $user, ?string $categoryName, string $type): ?TransactionCategory
    {
        $query = TransactionCategory::forUser($user->id)->where('type', $type);

        if ($categoryName) {
            $byName = (clone $query)
                ->where(function ($q) use ($categoryName) {
                    $q->where('name', 'like', "%{$categoryName}%")
                      ->orWhereRaw('LOWER(?) LIKE CONCAT("%", LOWER(name), "%")', [$categoryName]);
                })
                ->first();
            if ($byName) return $byName;
        }

        return (clone $query)->where('name', 'Lainnya')->first();
    }
OLD;

$new = <<<'NEW'
    private function resolveCategory(User $user, ?string $categoryName, string $type): ?TransactionCategory
    {
        $categories = TransactionCategory::forUser($user->id)->where('type', $type);

        if ($categoryName) {
            $needle = mb_strtolower(trim($categoryName));

            $byName = $categories->first(function ($cat) use ($needle) {
                $catName = mb_strtolower($cat->name);
                return str_contains($catName, $needle) || str_contains($needle, $catName);
            });

            if ($byName) return $byName;
        }

        return $categories->first(fn($cat) => $cat->name === 'Lainnya');
    }
NEW;

$files = [
    '/var/www/monexa/app/Services/CuanAiService.php',
    '/var/www/monexa/app/Services/WaParserService.php',
];

foreach ($files as $file) {
    if (! file_exists($file)) {
        echo "SKIP (tidak ditemukan): $file\n";

        continue;
    }

    $content = file_get_contents($file);

    if (strpos($content, $old) === false) {
        echo "⚠️  PATTERN TIDAK KETEMU PERSIS di: $file — kirim isi method resolveCategory() dari file ini, jangan lanjut dulu.\n";

        continue;
    }

    copy($file, $file.'.bak_'.date('Ymd_His'));

    $newContent = str_replace($old, $new, $content);
    file_put_contents($file, $newContent);

    echo "OK: $file (ter-patch, backup dibuat)\n";
}
