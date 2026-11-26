<?php

declare(strict_types=1);

namespace StageArt\Application\Accounting;

use StageArt\Domain\Account\AccountType;

/**
 * The preset Account set: AccountConsistencyPolicy.md / BudgetConsistencyPolicy.md
 * "Initial Account Set" as the specs list it today (the preset is to be
 * reviewed during development - change it here, never by renaming an
 * Organization's existing Accounts).
 *
 * Budget items (REVENUE / EXPENSE presets) carry the plain-language label
 * and hint the Budget screen shows, taken from BudgetManagementPolicy.md
 * "Standard Expense Categories" / "Standard Revenue Categories". Keys are
 * stable identifiers for the API; names are matched against existing
 * Accounts (name + type), so an Organization that already has, say, a
 * 「会場費」 EXPENSE Account keeps using it.
 *
 * Not presets: 元入金 (created with the opening balance by
 * CreateOrganizationUseCase) and the legacy 「車両交通費」 (kept as-is per
 * AccountConsistencyPolicy.md Version 1.1, shown as an extra Budget item
 * only where an Organization already has it).
 */
final class PresetAccountCatalog
{
    /**
     * @return PresetAccount[] in display order
     */
    public static function all(): array
    {
        return [
            new PresetAccount('cash', '現金', AccountType::ASSET),
            new PresetAccount('bank', '預金', AccountType::ASSET),
            new PresetAccount('receivable', '未収金', AccountType::ASSET),
            new PresetAccount('payable', '未払金', AccountType::LIABILITY),

            new PresetAccount('ticket_revenue', 'チケット売上', AccountType::REVENUE, 'チケット収入', 'チケットの販売で見込める収入'),
            new PresetAccount('merchandise_revenue', '物販売上', AccountType::REVENUE, '物販売上', 'グッズ・台本などの販売'),
            new PresetAccount('other_revenue', 'その他収入', AccountType::REVENUE, 'その他の収入', '助成金・補助金、協賛・寄付など'),

            new PresetAccount('venue', '会場費', AccountType::EXPENSE, '会場費', '劇場・ホール・稽古場の使用料'),
            new PresetAccount('equipment_rental', '機器レンタル費', AccountType::EXPENSE, '機器レンタル費', '照明・音響・映像機材などのレンタル'),
            new PresetAccount('outsourcing', '外注費', AccountType::EXPENSE, '外注費（スタッフ・キャスト）', 'スタッフ・キャストへの謝礼、外部への依頼'),
            new PresetAccount('advertising', '広告宣伝費', AccountType::EXPENSE, '広告宣伝費', 'フライヤー・ポスターの印刷、広告など'),
            new PresetAccount('communication', '通信費', AccountType::EXPENSE, '通信費', '郵送・電話・インターネットなど'),
            new PresetAccount('travel', '旅費交通費', AccountType::EXPENSE, '旅費交通費', '公共交通機関・レンタカー・駐車場など'),
            new PresetAccount('consumables', '消耗品費', AccountType::EXPENSE, '消耗品費', '日用品・事務用品などの物品の購入'),
            new PresetAccount('miscellaneous', 'その他雑費', AccountType::EXPENSE, 'その他雑費', '大道具・小道具・消耗品など、上記以外の費用'),
        ];
    }

    public static function findByKey(string $key): ?PresetAccount
    {
        foreach (self::all() as $preset) {
            if ($preset->key === $key) {
                return $preset;
            }
        }

        return null;
    }
}
