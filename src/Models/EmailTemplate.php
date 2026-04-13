<?php

namespace App\Models;

use App\Core\Database;

class EmailTemplate
{
    public static function findByKey(string $key): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM email_templates WHERE template_key = ?",
            [$key]
        );
    }

    public static function findAll(): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM email_templates ORDER BY name"
        );
    }

    public static function update(string $key, array $data): void
    {
        $sets = [];
        $params = [];
        foreach ($data as $col => $value) {
            $sets[] = "{$col} = ?";
            $params[] = $value;
        }
        $params[] = $key;
        Database::getInstance()->query(
            "UPDATE email_templates SET " . implode(', ', $sets) . " WHERE template_key = ?",
            $params
        );
    }

    /**
     * Render subject with placeholder substitution.
     */
    public static function renderSubject(string $key, string $lang, array $vars): ?string
    {
        $tpl = self::findByKey($key);
        if (!$tpl) return null;

        $field = $lang === 'en' ? 'subject_en' : 'subject_pl';
        return self::replacePlaceholders($tpl[$field], $vars);
    }

    /**
     * Render body with placeholder substitution.
     */
    public static function renderBody(string $key, string $lang, array $vars): ?string
    {
        $tpl = self::findByKey($key);
        if (!$tpl) return null;

        $field = $lang === 'en' ? 'body_en' : 'body_pl';
        return self::replacePlaceholders($tpl[$field], $vars);
    }

    /**
     * Replace {{placeholder}} tokens with values.
     */
    private static function replacePlaceholders(string $text, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $text = str_replace('{{' . $key . '}}', htmlspecialchars((string) $value), $text);
        }
        return $text;
    }
}
