<?php

declare(strict_types=1);

it('serves the SPA shell on the admin path', function (): void {
    $response = $this->get('/admin');

    $response->assertOk();
    $response->assertViewIs('admin::shell');
});

it('serves the SPA shell on any sub-path', function (): void {
    $response = $this->get('/admin/resources/users/42');

    $response->assertOk();
});

it('exposes explicit asset list to the shell view', function (): void {
    config()->set('admin.assets.css', ['/build/admin.css']);
    config()->set('admin.assets.js', ['/build/admin.js']);

    $response = $this->get('/admin');

    $response->assertOk();
    $assets = $response->viewData('assets');
    expect($assets)->toMatchArray([
        'css' => ['/build/admin.css'],
        'js' => ['/build/admin.js'],
    ]);
});

it('resolves css/js from a Vite manifest', function (): void {
    $manifest = [
        'resources/js/admin.js' => [
            'file' => 'assets/admin-AAA.js',
            'isEntry' => true,
            'imports' => ['_shared-BBB.js'],
            'css' => ['assets/admin-CCC.css'],
        ],
        '_shared-BBB.js' => [
            'file' => 'assets/shared-BBB.js',
            'css' => ['assets/shared-DDD.css'],
        ],
    ];
    $manifestPath = sys_get_temp_dir().'/admin-vite-manifest-'.uniqid().'.json';
    file_put_contents($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR));

    config()->set('admin.assets.vite_manifest', $manifestPath);
    config()->set('admin.assets.vite_entry', 'resources/js/admin.js');
    config()->set('admin.assets.vite_base_url', '/build/');

    $response = $this->get('/admin');

    @unlink($manifestPath);

    $response->assertOk();
    $assets = $response->viewData('assets');
    // shared chunk visited первым (depth-first по imports), потом entry.
    expect($assets['js'])->toBe([
        '/build/assets/shared-BBB.js',
        '/build/assets/admin-AAA.js',
    ]);
    expect($assets['css'])->toBe([
        '/build/assets/shared-DDD.css',
        '/build/assets/admin-CCC.css',
    ]);
});

it('returns empty assets when neither config-list nor vite-manifest provided', function (): void {
    $response = $this->get('/admin');

    $response->assertOk();
    expect($response->viewData('assets'))->toBe([
        'css' => [],
        'js' => [],
    ]);
});

/*
 * Плашка установки.
 *
 * Рисуется оболочкой, а не приложением: объявление вроде «данные стираются
 * каждый час» — свойство установки, и человеку важнее всего увидеть его
 * именно тогда, когда SPA не поднялась.
 */

it('без текста не печатает плашку вовсе', function (): void {
    // Пустая плашка на каждой странице каждой установки — это разметка,
    // которая ничего не значит, и место, где однажды окажется чужой текст.
    config()->set('admin.notice.text', null);

    $html = (string) $this->get('/admin')->getContent();

    expect($html)->not->toContain('admin-notice');
});

it('печатает текст плашки, когда он задан', function (): void {
    config()->set('admin.notice.text', 'Демонстрационный стенд: данные стираются');

    $html = (string) $this->get('/admin')->getContent();

    expect($html)->toContain('Демонстрационный стенд: данные стираются');
});

it('отсчёт ведётся от серверной метки, а не от часов посетителя', function (): void {
    // Часы у посетителей расходятся, и «осталось 40 минут», посчитанное по
    // ним, означало бы что угодно. Сервер называет момент, браузер считает
    // разницу.
    $until = now()->addMinutes(17)->toIso8601String();
    config()->set('admin.notice.text', 'Сброс стенда');
    config()->set('admin.notice.countdown_to', $until);

    $html = (string) $this->get('/admin')->getContent();

    expect($html)->toContain('data-until="'.$until.'"');
});

it('без момента отсчёта не печатает ни разметки отсчёта, ни скрипта', function (): void {
    config()->set('admin.notice.text', 'Просто объявление');
    config()->set('admin.notice.countdown_to', null);

    $html = (string) $this->get('/admin')->getContent();

    expect($html)->toContain('Просто объявление')
        ->and($html)->not->toContain('admin-notice-left');
});

it('плашка стоит выше приложения, а не внутри него', function (): void {
    // Внутри #admin-app её снесло бы первым же рендером SPA — ровно тогда,
    // когда она нужна.
    config()->set('admin.notice.text', 'Стенд');

    $html = (string) $this->get('/admin')->getContent();

    expect(strpos($html, 'admin-notice'))->toBeLessThan(strpos($html, 'id="admin-app"'));
});
