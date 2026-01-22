<?php

namespace App\Authentication\Passwords;

use CodeIgniter\Shield\Authentication\Passwords\NothingPersonalValidator as ShieldValidator;
use CodeIgniter\Shield\Entities\User;

class NothingPersonalValidator extends ShieldValidator
{
    protected function isNotPersonal(string $password, ?User $user): bool
    {
        $userName = strtolower($user->username ?? '');
        $email    = strtolower($user->email ?? '');
        $valid    = true;

        // The most obvious transgressions
        if (
            $password === $userName
            || $password === $email
            || $password === strrev($userName)
        ) {
            $valid = false;
        }

        // Parse out as many pieces as possible from username, password and email.
        // Use the pieces as needles and haystacks and look every which way for matches.
        if ($valid) {
            // Take username apart for use as search needles
            $needles = $this->strip_explode($userName);

            // extract local-part and domain parts from email as separate needles
            [$localPart, $domain] = explode('@', $email) + [1 => null];

            // might be john.doe@example.com and we want all the needles we can get
            $emailParts = $this->strip_explode($localPart);
            if ($domain !== null && $domain !== '') {
                $emailParts[] = $domain;
            }
            $needles = [...$needles, ...$emailParts];

            // Get any other "personal" fields defined in config
            $personalFields = $this->config->personalFields;

            foreach ($personalFields as $value) {
                if (! empty($user->{$value})) {
                    $needles[] = strtolower($user->{$value});
                }
            }

            $trivial = [
                'a',
                'an',
                'and',
                'as',
                'at',
                'but',
                'for',
                'if',
                'in',
                'not',
                'of',
                'or',
                'so',
                'the',
                'then',
                'then',
            ];

            // Make password into haystacks
            $haystacks = $this->strip_explode($password);

            foreach ($haystacks as $haystack) {
                if (empty($haystack) || in_array($haystack, $trivial, true) || mb_strlen($haystack, 'UTF-8') < 3) {
                    continue;  // ignore trivial words
                }

                foreach ($needles as $needle) {
                    if (empty($needle) || in_array($needle, $trivial, true) || mb_strlen($needle, 'UTF-8') < 3) {
                        continue;
                    }

                    // look both ways in case password is subset of needle
                    if (
                        str_contains($haystack, $needle)
                        || str_contains($needle, $haystack)
                    ) {
                        $valid = false;
                        break 2;
                    }
                }
            }
        }
        if ($valid) {
            return true;
        }

        $this->error      = lang('Auth.errorPasswordPersonal');
        $this->suggestion = lang('Auth.suggestPasswordPersonal');

        return false;
    }
}
