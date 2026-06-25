<?php
declare(strict_types=1);

namespace App\About;

use App\Core\Response;
use App\Core\View;

final class AboutController
{
    public function __construct(private View $view) {}

    public function show(): Response
    {
        return Response::html($this->view->render('about', ['title' => 'About Crush']));
    }
}
