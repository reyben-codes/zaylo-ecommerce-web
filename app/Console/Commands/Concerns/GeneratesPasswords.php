<?php

namespace App\Console\Commands\Concerns;

trait GeneratesPasswords
{
    /**
     * Meets StrongPassword (8+ characters with upper, lower, and a symbol) using only symbols that are easy to copy and type.
     */
    protected function generatePassword(): string
    {
        $sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnopqrstuvwxyz', '23456789', '!@#%*-_.'];
        $characters = array_map(fn (string $set) => $set[random_int(0, strlen($set) - 1)], $sets);
        $all = implode('', $sets);
        while (count($characters) < 18) {
            $characters[] = $all[random_int(0, strlen($all) - 1)];
        }
        for ($i = count($characters) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$characters[$i], $characters[$j]] = [$characters[$j], $characters[$i]];
        }

        return implode('', $characters);
    }
}
