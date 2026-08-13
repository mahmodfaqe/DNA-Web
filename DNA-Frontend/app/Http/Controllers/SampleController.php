<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

/**
 * The teaching samples.
 *
 * Two ways in. Downloading gives a file to upload into the analysis tab, which
 * is the same journey a reader's own data takes. Loading pre-fills the cloning
 * form, which matters more than it sounds: the trap sample only springs if the
 * reader arrives with EcoRI already chosen, and a reader asked to configure it
 * themselves will pick something else and never meet the lesson.
 */
class SampleController extends Controller
{
    private function path(string $file): string
    {
        // Whitelisted against the manifest rather than sanitised: a filename
        // that is not in config/samples.php has no business being read, and
        // checking membership is a stronger guarantee than stripping "..".
        $known = collect(config('samples'))
            ->flatten(1)
            ->pluck('file')
            ->contains($file);

        abort_unless($known, 404);

        $path = resource_path('samples/' . $file);
        abort_unless(File::exists($path), 404);

        return $path;
    }

    public function download(string $file): Response
    {
        return $this->textDownload($file, File::get($this->path($file)));
    }

    /**
     * Open the cloning form with this sample and its exercise settings already
     * filled in.
     */
    public function load(string $file): mixed
    {
        $sample = collect(config('samples.cloning'))->firstWhere('file', $file);
        abort_if($sample === null, 404);

        $sequence = collect(preg_split('/\R/', File::get($this->path($file))) ?: [])
            ->reject(fn (string $line) => str_starts_with(trim($line), '>'))
            ->implode('');

        return redirect()
            ->route('cloning.index')
            ->withInput(array_merge(
                $sample['settings'] ?? [],
                ['sequence' => $sequence, 'label' => __('samples.' . $sample['key'] . '.title')],
            ));
    }
}
