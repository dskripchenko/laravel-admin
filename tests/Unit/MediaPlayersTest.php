<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\FileUpload;
use Dskripchenko\LaravelAdmin\Infolist\AudioEntry;
use Dskripchenko\LaravelAdmin\Infolist\FieldEntry;
use Dskripchenko\LaravelAdmin\Infolist\VideoEntry;
use Dskripchenko\LaravelAdmin\Layout\Audio;
use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Layout\Video;
use Dskripchenko\LaravelAdmin\Support\MediaUrl;
use Dskripchenko\LaravelAdmin\Table\TableColumn;

beforeEach(function (): void {
    config([
        'filesystems.disks.public' => [
            'driver' => 'local',
            'root' => sys_get_temp_dir(),
            'url' => 'https://cdn.test/storage',
            'visibility' => 'public',
        ],
        'filesystems.disks.local' => ['driver' => 'local', 'root' => sys_get_temp_dir()],
    ]);
});

/* ---------------- addresses ---------------- */

it('tells media addresses from state keys', function (string $value, bool $media): void {
    expect(MediaUrl::looksLikeMedia($value))->toBe($media);
})->with([
    'url' => ['https://cdn.test/a.mp4', true],
    'root-relative' => ['/media/a', true],
    'storage path' => ['videos/intro', true],
    'bare file' => ['intro.mp4', true],
    'hls' => ['master.m3u8?token=1', true],
    'data uri' => ['data:audio/wav;base64,AA', true],
    'state key' => ['recording', false],
    'dotted state key' => ['media.url', false],
    'snake key' => ['call_recording', false],
    'empty' => ['', false],
]);

it('resolves storage paths through a public disk and leaves urls alone', function (): void {
    expect(MediaUrl::resolve('videos/a b.mp4', 'public'))->toBe('https://cdn.test/storage/videos/a b.mp4')
        ->and(MediaUrl::resolve('https://x.test/a.mp4', 'public'))->toBe('https://x.test/a.mp4')
        ->and(MediaUrl::resolve('/media/a.mp4', 'public'))->toBe('/media/a.mp4')
        ->and(MediaUrl::resolve('videos/a.mp4', null))->toBe('videos/a.mp4');
});

it('streams a local disk without a public url through the panel', function (): void {
    expect(MediaUrl::servedByPanel('local'))->toBeTrue()
        ->and(MediaUrl::servedByPanel('public'))->toBeFalse()
        ->and(MediaUrl::resolve('calls/1.ogg', 'local'))
        ->toBe('/api/admin/uploads/serve?disk=local&path=calls%2F1.ogg');
});

it('describes a disk for the browser as a prefix, a suffix and an encoding', function (): void {
    expect(MediaUrl::base('public'))->toBe([
        'prefix' => 'https://cdn.test/storage/',
        'suffix' => '',
        'encode' => 'path',
    ])->and(MediaUrl::base('local'))->toBe([
        'prefix' => '/api/admin/uploads/serve?disk=local&path=',
        'suffix' => '',
        'encode' => 'component',
    ]);
});

it('refuses an unknown disk', function (): void {
    MediaUrl::base('nowhere');
})->throws(InvalidArgumentException::class);

/* ---------------- Layout::video ---------------- */

it('serializes a video with its tracks, poster and options', function (): void {
    $array = Layout::video('videos/intro.mp4')->disk('public')
        ->title('Intro')
        ->poster('videos/intro.jpg')
        ->subtitles('videos/intro.en.vtt', 'en', 'English', default: true)
        ->chapters('videos/intro.chapters.vtt')
        ->thumbnails('videos/intro.thumbs.vtt')
        ->autoplay()
        ->loop()
        ->resume('intro')
        ->aspectRatio('4:3')
        ->maxWidth(720)
        ->hlsOptions(['preferNative' => false, 'audioLanguage' => 'ru'])
        ->toArray();

    expect(Layout::video('a.mp4'))->toBeInstanceOf(Video::class)
        ->and($array['type'])->toBe('video')
        ->and($array['sources'])->toBe([['src' => 'https://cdn.test/storage/videos/intro.mp4']])
        ->and($array['poster'])->toBe('https://cdn.test/storage/videos/intro.jpg')
        ->and($array['thumbnails'])->toBe('https://cdn.test/storage/videos/intro.thumbs.vtt')
        ->and($array['tracks'])->toBe([
            ['src' => 'https://cdn.test/storage/videos/intro.en.vtt', 'kind' => 'subtitles', 'srclang' => 'en', 'label' => 'English', 'default' => true],
            ['src' => 'https://cdn.test/storage/videos/intro.chapters.vtt', 'kind' => 'chapters'],
        ])
        ->and($array['title'])->toBe('Intro')
        ->and($array['autoplay'])->toBeTrue()
        // Browsers allow autoplay only muted.
        ->and($array['muted'])->toBeTrue()
        ->and($array['loop'])->toBeTrue()
        ->and($array['resumeKey'])->toBe('intro')
        ->and($array['aspectRatio'])->toBe('4 / 3')
        ->and($array['maxWidth'])->toBe('720px')
        // Not localized as captions, and sent once.
        ->and($array['hlsOptions'])->toBe(['preferNative' => false, 'audioLanguage' => 'ru'])
        ->and($array['props'])->not->toHaveKey('hlsOptions')
        ->and($array['props'])->not->toHaveKey('sources')
        ->and($array['source'])->toBeNull()
        ->and($array['disk'])->toBeNull();
});

it('takes qualities as a list of sources, and HLS by type or extension', function (): void {
    $array = Layout::video([
        ['src' => '/media/intro-720.mp4', 'label' => '720p'],
        ['src' => '/media/intro-360.mp4', 'label' => '360p', 'type' => 'video/mp4'],
        '/media/intro.webm',
    ])->hls('/streams/intro/playlist', 'Auto')->toArray();

    expect($array['sources'])->toBe([
        ['src' => '/media/intro-720.mp4', 'label' => '720p'],
        ['src' => '/media/intro-360.mp4', 'type' => 'video/mp4', 'label' => '360p'],
        ['src' => '/media/intro.webm'],
        ['src' => '/streams/intro/playlist', 'type' => 'application/x-mpegURL', 'label' => 'Auto'],
    ]);

    $mime = Layout::video('/media/a')->mime('video/webm')->toArray();
    expect($mime['sources'][0]['type'])->toBe('video/webm');
});

it('reads the video from the state when given a key', function (): void {
    $array = Layout::video('recording')->disk('local')->toArray();

    expect($array['source'])->toBe('recording')
        ->and($array['sources'])->toBe([])
        ->and($array['disk'])->toBe([
            'prefix' => '/api/admin/uploads/serve?disk=local&path=',
            'suffix' => '',
            'encode' => 'component',
        ]);

    expect(Layout::video()->fromState('media.url')->toArray()['source'])->toBe('media.url');
});

it('rejects what a video cannot be', function (Closure $build): void {
    $build();
})->with([
    'preload' => [fn () => Layout::video('a.mp4')->preload('eager')],
    'aspect ratio' => [fn () => Layout::video('a.mp4')->aspectRatio('wide')],
    'track kind' => [fn () => Layout::video('a.mp4')->tracks([['src' => 'a.vtt', 'kind' => 'metadata']])],
    'source shape' => [fn () => Layout::video([['url' => 'a.mp4']])],
    'crossorigin' => [fn () => Layout::video('a.mp4')->crossorigin('maybe')],
])->throws(InvalidArgumentException::class);

/* ---------------- Layout::audio ---------------- */

it('serializes a single audio track with a waveform', function (): void {
    $array = Layout::audio('/media/episode.mp3')
        ->title('Episode 12')
        ->artist('The Podcast')
        ->waveform()
        ->peaks([0.1, 2, -1, '0.5'])
        ->compact()
        ->rates([1, 1.5, 2])
        ->toArray();

    expect(Layout::audio('a.mp3'))->toBeInstanceOf(Audio::class)
        ->and($array['type'])->toBe('audio')
        ->and($array['tracks'])->toBe([['src' => '/media/episode.mp3']])
        ->and($array['title'])->toBe('Episode 12')
        ->and($array['artist'])->toBe('The Podcast')
        ->and($array['waveform'])->toBeTrue()
        ->and($array['peaks'])->toBe([0.1, 1.0, 0.0, 0.5])
        ->and($array['props'])->not->toHaveKey('peaks')
        ->and($array['showVolume'])->toBeFalse()
        ->and($array['showRate'])->toBeFalse()
        ->and($array['rates'])->toBe([1.0, 1.5, 2.0]);
});

it('serializes a playlist and the mini variant', function (): void {
    $array = Audio::make([
        ['src' => 'audio/a.mp3', 'title' => 'Intro', 'artist' => 'Band', 'duration' => 64, 'peaks' => [0.2, 0.4]],
        'audio/b.ogg',
    ])->disk('public')->mime('audio/ogg')->mini()->playlist(false)->autoAdvance(false)->toArray();

    expect($array['tracks'])->toBe([
        ['src' => 'https://cdn.test/storage/audio/a.mp3', 'title' => 'Intro', 'artist' => 'Band', 'duration' => 64, 'peaks' => [0.2, 0.4], 'type' => 'audio/ogg'],
        ['src' => 'https://cdn.test/storage/audio/b.ogg', 'type' => 'audio/ogg'],
    ])
        ->and($array['variant'])->toBe('mini')
        ->and($array['showPlaylist'])->toBeFalse()
        ->and($array['autoAdvance'])->toBeFalse()
        ->and($array['peaks'])->toBeNull();
});

it('reads the audio from the state when given a key', function (): void {
    $array = Layout::audio('recordings')->disk('public')->toArray();

    expect($array['source'])->toBe('recordings')
        ->and($array['tracks'])->toBe([])
        ->and($array['disk']['prefix'])->toBe('https://cdn.test/storage/');
});

/* ---------------- entries ---------------- */

it('serializes the video and audio entries with the disk for the browser', function (): void {
    $video = VideoEntry::make('clip')->disk('public')->posterFrom('cover')->aspectRatio('4:3')->maxWidth(480)->resume()->toArray();
    $audio = AudioEntry::make('call')->disk('local')->full()->waveform()->peaksFrom('call_peaks')->toArray();

    expect($video['type'])->toBe('video')
        ->and($video['attributes']['disk']['prefix'])->toBe('https://cdn.test/storage/')
        ->and($video['attributes']['posterFrom'])->toBe('cover')
        ->and($video['attributes']['aspectRatio'])->toBe('4 / 3')
        ->and($video['attributes']['maxWidth'])->toBe('480px')
        ->and($video['attributes']['resume'])->toBeTrue()
        ->and($audio['type'])->toBe('audio')
        ->and($audio['attributes']['disk']['encode'])->toBe('component')
        ->and($audio['attributes']['variant'])->toBe('default')
        ->and($audio['attributes']['waveform'])->toBeTrue()
        ->and($audio['attributes']['peaksFrom'])->toBe('call_peaks');
});

it('shows a file field on the view page through its own entry', function (): void {
    $resource = new class extends Dskripchenko\LaravelAdmin\Resource\Resource
    {
        public static string $model = Illuminate\Database\Eloquent\Model::class;

        public function fields(): array
        {
            return [FileUpload::make('recording')->accept('audio/*')];
        }
    };

    $entries = $resource->infolist();

    expect($entries[0])->toBeInstanceOf(FieldEntry::class)
        ->and($entries[0]->toArray()['attributes']['field']['type'])->toBe('file');
});

/* ---------------- table columns ---------------- */

it('serializes the audio and video column presets', function (): void {
    $audio = TableColumn::make('recording')->asAudio('public', waveform: true)->toArray();
    $video = TableColumn::make('clip')->asVideo(posterColumn: 'cover_url', width: 96)->toArray();
    $plain = TableColumn::make('clip')->asVideo()->toArray();

    expect($audio['type'])->toBe('audio')
        ->and($audio['meta'])->toBe([
            'disk' => ['prefix' => 'https://cdn.test/storage/', 'suffix' => '', 'encode' => 'path'],
            'waveform' => true,
        ])
        ->and($video['type'])->toBe('video')
        ->and($video['meta'])->toBe(['poster' => 'cover_url', 'width' => 96, 'height' => 54])
        ->and($plain['meta'])->toBe(['width' => 64, 'height' => 36]);
});
