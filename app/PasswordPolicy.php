<?php
// app/PasswordPolicy.php — regras de senha forte
declare(strict_types=1);

final class PasswordPolicy
{
    public const MIN_LENGTH = 10;

    /** Senhas mais usadas no Brasil e no mundo (bloqueadas). */
    private const COMMON = [
        '123456', '1234567', '12345678', '123456789', '1234567890', '12345678910', '0123456789',
        'senha', 'senha123', 'senha1234', 'senha12345', 'password', 'password1', 'password123',
        'qwerty', 'qwerty123', 'abc123', 'abcd1234', 'iloveyou', 'admin', 'admin123', 'administrador',
        'jesus', 'jesus123', 'jesuscristo', 'jesusteama', 'jesusmeama', 'deusefiel', 'deuseamor',
        'deusefiel123', 'aleluia', 'gloriaadeus', 'assembleia', 'assembleiadedeus', 'admoema',
        'midia123', 'mudar123', 'trocar123', 'brasil', 'brasil123', 'flamengo', 'corinthians',
        'palmeiras', 'saopaulo', 'santos', 'vasco', 'gremio', 'cruzeiro', 'botafogo', 'internacional',
        '11111111', '00000000', '12121212', '88888888', '987654321', 'qwertyuiop', 'asdfghjkl',
        'minhasenha', 'mudarsenha', 'teste123', 'teste1234', 'welcome', 'welcome1', 'monkey', 'dragon',
    ];

    /** Lista de problemas encontrados (vazia = senha aceita). */
    public static function validate(string $password, array $context = []): array
    {
        $errors = [];
        if (mb_strlen($password) < self::MIN_LENGTH) {
            $errors[] = 'A senha precisa ter pelo menos ' . self::MIN_LENGTH . ' caracteres.';
        }
        if (mb_strlen($password) > 128) {
            $errors[] = 'A senha pode ter no máximo 128 caracteres.';
        }
        $classes = 0;
        $classes += preg_match('/[a-z]/', $password) ? 1 : 0;
        $classes += preg_match('/[A-Z]/', $password) ? 1 : 0;
        $classes += preg_match('/\d/', $password) ? 1 : 0;
        $classes += preg_match('/[^a-zA-Z\d]/', $password) ? 1 : 0;
        if ($classes < 3) {
            $errors[] = 'Use pelo menos 3 destes tipos: letra minúscula, letra maiúscula, número e símbolo.';
        }
        $normalized = mb_strtolower(preg_replace('/[\s\W_]+/u', '', $password));
        if (in_array($normalized, self::COMMON, true) || in_array(mb_strtolower($password), self::COMMON, true)) {
            $errors[] = 'Esta senha é muito comum. Escolha outra.';
        }
        foreach ($context as $word) {
            $word = mb_strtolower((string) $word);
            foreach (preg_split('/[\s@._\-]+/', $word) as $piece) {
                if (mb_strlen($piece) >= 4 && str_contains(mb_strtolower($password), $piece)) {
                    $errors[] = 'A senha não pode conter seu nome ou e-mail.';
                    break 2;
                }
            }
        }
        if (preg_match('/(.)\1{3,}/', $password)) {
            $errors[] = 'Evite repetir o mesmo caractere várias vezes seguidas.';
        }
        if (!$errors && Config::bool('PASSWORD_PWNED_CHECK', false) && self::isPwned($password)) {
            $errors[] = 'Esta senha já apareceu em vazamentos de dados na internet. Escolha outra.';
        }
        return $errors;
    }

    /**
     * Consulta Have I Been Pwned por k-anonimato: só os 5 primeiros caracteres
     * do SHA-1 saem do servidor. Em caso de falha de rede, não bloqueia.
     */
    private static function isPwned(string $password): bool
    {
        if (!function_exists('curl_init')) {
            return false;
        }
        $sha1 = strtoupper(sha1($password));
        $prefix = substr($sha1, 0, 5);
        $suffix = substr($sha1, 5);
        $ch = curl_init('https://api.pwnedpasswords.com/range/' . $prefix);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 4,
            CURLOPT_HTTPHEADER     => ['Add-Padding: true', 'User-Agent: ADMoema-Midia'],
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $status !== 200) {
            return false;
        }
        foreach (explode("\n", (string) $body) as $line) {
            [$hashSuffix, $count] = array_pad(explode(':', trim($line)), 2, '0');
            if ($hashSuffix === $suffix && (int) $count > 0) {
                return true;
            }
        }
        return false;
    }

    /** Senha provisória legível (sem caracteres ambíguos como 0/O, 1/l). */
    public static function temporary(): string
    {
        $sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnopqrstuvwxyz', '23456789', '!@#$%*?'];
        $chars = [];
        foreach ($sets as $set) {
            $chars[] = $set[random_int(0, strlen($set) - 1)];
        }
        $all = implode('', $sets);
        while (count($chars) < 14) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }
        // Embaralha com gerador seguro
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }
        return implode('', $chars);
    }
}
