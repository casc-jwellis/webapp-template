<?php

declare(strict_types=1);

namespace App\Core;

final class Auth
{
    /** Verified against when the username is unknown, so response time doesn't reveal valid usernames. */
    private const DUMMY_HASH = '$2y$10$Vrjs0nLJzb4XKGGCGfXemeF0rtT4UsHSLdACslO/ErltPwcyVojUW';

    /** Longest password that can be set. Counted in bytes, like the minimum: the password hash only looks at the first 72, so this stays safely inside it. */
    public const MAX_PASSWORD = 64;
    /** Shortest password that can be set (counted in bytes, like the maximum). */
    public const MIN_PASSWORD = 10;

    private ?array $user = null;
    private bool $loaded = false;

    public function __construct(
        private Database $db,
        private Session $session,
        private Config $config,
        private Csrf $csrf,
    ) {
    }

    /** @return array<string,mixed>|null the logged-in user (never includes the password hash) */
    public function user(): ?array
    {
        if ($this->loaded) {
            return $this->user;
        }
        $this->loaded = true;

        $uid = $this->session->get('auth.uid');
        if (!is_int($uid) || $uid < 1) {
            return null;
        }

        $idle = (int) $this->config->get('session.idle_timeout', 1800);
        $seen = (int) $this->session->get('auth.seen', 0);
        if ($idle > 0 && time() - $seen > $idle) {
            $this->logout();
            $this->session->start();
            $this->session->flash('info', 'You were signed out due to inactivity.');
            return null;
        }

        // Reloaded each request so deleted/changed/disabled accounts take effect immediately.
        $row = $this->db->one('SELECT * FROM users WHERE user_id = ?', [$uid]);
        if ($row === null || !empty($row['disabled'])) {
            $disabled = $row !== null;
            $this->logout();
            $this->session->start();
            if ($disabled) {
                $this->session->flash('danger', 'Your account has been disabled.');
            }
            return null;
        }
        unset($row['password']); // never keep the hash around
        $row['admin'] = (bool) $row['admin'];
        $row['disabled'] = false;
        $this->session->set('auth.seen', time());
        return $this->user = $row;
    }

    /** Must this user choose a new password before doing anything else? */
    public function mustChangePassword(): bool
    {
        return !empty($this->user()['must_change_password']);
    }

    /** Called once the person has chosen their own password. */
    public function clearMustChangePassword(int $userId): void
    {
        $this->db->execute('UPDATE users SET must_change_password = 0 WHERE user_id = ?', [$userId]);
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function isAdmin(): bool
    {
        return (bool) ($this->user()['admin'] ?? false);
    }

    public function attempt(string $username, string $password): bool
    {
        $row = $username === '' ? null : $this->db->one('SELECT * FROM users WHERE username = ?', [$username]);

        $stored = (string) ($row['password'] ?? self::DUMMY_HASH);
        // Disabled accounts fail exactly like a wrong password (no hint to someone guessing).
        $ok = password_verify($password, $stored) && $row !== null && empty($row['disabled']);

        if (!$ok) {
            usleep(random_int(200_000, 400_000)); // slow down online guessing
            return false;
        }

        // Re-hash now that we have the plaintext if PHP's default algorithm or cost has moved on.
        if (password_needs_rehash($stored, PASSWORD_DEFAULT)) {
            $this->setPassword((int) $row['user_id'], $password);
        }

        $this->signIn((int) $row['user_id']);
        return true;
    }

    /** Start the session for someone who has already proved who they are (a correct password, or Google). */
    public function signIn(int $userId): void
    {
        $this->session->regenerate(); // prevent session fixation
        $this->csrf->rotate();
        $this->session->set('auth.uid', $userId);
        $this->session->set('auth.seen', time());
        $this->loaded = false;
        $this->user = null;

        $this->db->execute('UPDATE users SET lastlogin = ? WHERE user_id = ?', [date('Y-m-d H:i:s'), $userId]);
    }

    /**
     * Every account whose email address is this one (ignoring capitals). Normally zero or one; Google sign-in only
     * proceeds on exactly one. Never includes the password hash.
     *
     * @return list<array<string,mixed>>
     */
    public function usersByEmail(string $email): array
    {
        if ($email === '') {
            return [];
        }
        $rows = $this->db->all('SELECT * FROM users WHERE LOWER(email) = LOWER(?)', [$email]);
        foreach ($rows as &$row) {
            unset($row['password']);
        }
        return $rows;
    }

    /**
     * A temporary password was emailed to this person, but they have just proved who they are another way, so it is
     * no longer needed: make it unusable and drop the "must choose a new password" flag. A password can still be
     * set later with "Forgot password?".
     */
    public function retireTemporaryPassword(int $userId): void
    {
        $this->db->execute(
            'UPDATE users SET password = ?, must_change_password = 0 WHERE user_id = ?',
            [password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), $userId]
        );
    }

    public function logout(): void
    {
        $this->session->destroy();
        $this->user = null;
        $this->loaded = true;
    }

    /** Check a password for an existing user (used by "change password"). */
    public function verifyPassword(int $userId, string $password): bool
    {
        $stored = $this->db->value('SELECT password FROM users WHERE user_id = ?', [$userId]);
        return is_string($stored) && password_verify($password, $stored);
    }

    /** Store a password_hash() value. Returns false if it could not be saved (the reason is logged). */
    public function setPassword(int $userId, string $password): bool
    {
        try {
            $this->db->execute('UPDATE users SET password = ? WHERE user_id = ?', [password_hash($password, PASSWORD_DEFAULT), $userId]);
            return true;
        } catch (\PDOException $e) {
            error_log('setPassword failed: ' . $e->getMessage());
            return false;
        }
    }
}
