<?php
// app/Totp.php — códigos de 6 dígitos do 2FA (RFC 6238), sem biblioteca externa.
// Compatível com Google Authenticator, Microsoft Authenticator, Authy etc.
declare(strict_types=1);

final class Totp
{
    private const PERIOD = 30;
    private const DIGITS = 6;
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20)); // 160 bits
    }

    /** URI lida pelo aplicativo autenticador através do QR Code. */
    public static function uri(string $secret, string $account, string $issuer = 'Mídia ADMoema'): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
            . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::PERIOD;
    }

    /**
     * Confere o código aceitando 1 janela de tolerância (±30 s).
     * Devolve o "passo" de tempo usado, para impedir reutilização do mesmo código.
     */
    public static function verify(string $secret, string $code, ?int $lastStep = null): ?int
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== self::DIGITS) {
            return null;
        }
        $key = self::base32Decode($secret);
        $current = intdiv(time(), self::PERIOD);
        for ($offset = -1; $offset <= 1; $offset++) {
            $step = $current + $offset;
            if ($lastStep !== null && $step <= $lastStep) {
                continue; // código já usado
            }
            if (hash_equals(self::codeAt($key, $step), $code)) {
                return $step;
            }
        }
        return null;
    }

    private static function codeAt(string $key, int $step): string
    {
        $binStep = pack('N2', ($step >> 32) & 0xFFFFFFFF, $step & 0xFFFFFFFF);
        $hash = hash_hmac('sha1', $binStep, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);
        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    public static function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }
        return $out;
    }

    public static function base32Decode(string $data): string
    {
        $data = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $data));
        $bits = '';
        foreach (str_split($data) as $char) {
            $bits .= str_pad(decbin(strpos(self::ALPHABET, $char)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }

    /** 8 códigos de recuperação de uso único, no formato XXXX-XXXX. */
    public static function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $raw = strtoupper(bin2hex(random_bytes(4)));
            $codes[] = substr($raw, 0, 4) . '-' . substr($raw, 4, 4);
        }
        return $codes;
    }
}
