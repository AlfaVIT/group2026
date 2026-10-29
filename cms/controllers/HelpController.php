<?php

class HelpController
{
    public function index(): void
    {
        view('help', ['title' => 'Справка']);
    }
}