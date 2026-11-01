<?php
// app/EventNaming.php — sugestão automática do nome da pasta pelo dia da semana
declare(strict_types=1);

final class EventNaming
{
    /**
     * Agenda fixa da ADMoema. Índice = dia da semana (0 = domingo … 6 = sábado).
     * Dias com várias opções mostram a escolha ao usuário; dias vazios pedem um nome.
     */
    public const SCHEDULE = [
        0 => ['Culto com a Família'],
        1 => [],
        2 => ['Culto de Ensino'],
        3 => ['Círculo de Oração'],
        4 => ['Culto Público'],
        5 => [],
        6 => ['Culto dos Jovens', 'Culto dos Adolescentes', 'Culto dos Jovens e Adolescentes'],
    ];

    private const WEEKDAYS = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];

    public static function suggestionsFor(DateTimeImmutable $date): array
    {
        return self::SCHEDULE[(int) $date->format('w')];
    }

    public static function weekdayName(DateTimeImmutable $date): string
    {
        return self::WEEKDAYS[(int) $date->format('w')];
    }

    /** Valida e normaliza o nome digitado pelo usuário. Devolve [nome, erro]. */
    public static function cleanName(string $name): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        if (mb_strlen($name) < 3) {
            return [null, 'O nome da pasta precisa ter pelo menos 3 caracteres.'];
        }
        if (mb_strlen($name) > 80) {
            return [null, 'O nome da pasta pode ter no máximo 80 caracteres.'];
        }
        // Letras (com acento), números, espaço e pontuação simples
        if (!preg_match("/^[\\p{L}\\p{N} ().,'&ºª\\-]+$/u", $name)) {
            return [null, 'Use apenas letras, números, espaços e os sinais ( ) . , - & \'.'];
        }
        return [$name, null];
    }

    /** "Culto com a Família" → "culto-com-a-familia" */
    public static function slug(string $name): string
    {
        $ascii = $name;
        if (function_exists('transliterator_transliterate')) {
            $ascii = transliterator_transliterate('Any-Latin; Latin-ASCII', $name);
        } else {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
            if ($converted !== false) {
                $ascii = $converted;
            }
        }
        $ascii = strtolower($ascii);
        $ascii = preg_replace('/[^a-z0-9]+/', '-', $ascii);
        $ascii = trim($ascii, '-');
        return substr($ascii !== '' ? $ascii : 'evento', 0, 80);
    }

    public static function parseDate(?string $value): ?DateTimeImmutable
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            return null;
        }
        // Aceita de 2000 até 1 ano à frente
        $year = (int) $date->format('Y');
        if ($year < 2000 || $date > new DateTimeImmutable('+1 year')) {
            return null;
        }
        return $date;
    }
}
