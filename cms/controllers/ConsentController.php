<?php

class ConsentController
{
    public function index(): void
    {
        view('consent', ['title' => 'Согласие на обработку персональных данных'], false);
    }
}
