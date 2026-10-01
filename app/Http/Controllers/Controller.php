<?php


namespace App\Http\Controllers;

abstract class Controller
{
    // Shared across every controller: fail with both a human message and a
    // stable machine code, so the frontend can branch on error_code instead
    // of parsing text. (OrderController already defines its own private copy
    // of this — harmless duplication, no conflict; new controllers use this one.)
    protected function fail(string $message, string $code, int $status = 422): never
    {
        abort(response()->json(['message' => $message, 'error_code' => $code], $status));
    }
}
