<?php
/**
 * Copyright (c) Enalean, 2013. All Rights Reserved.
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */

require_once 'common/mvc2/Controller.class.php';
require_once 'LoginPresenter.class.php';

class User_LoginController extends MVC2_Controller {

    public function __construct(Codendi_Request $request) {
        parent::__construct('user', $request);
    }

    public function index($presenter) {
        $renderer = TemplateRendererFactory::build()->getRenderer($presenter->getTemplateDir());
        $renderer->renderToPage($presenter->getTemplate(), $presenter);
    }
}

?>
