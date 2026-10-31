<?php

it('la pagina de baja general de email lleva noindex', function () {
    $html = $this->get(route('email.baja-general.form'))->getContent();

    expect($html)->toContain('noindex');
});
