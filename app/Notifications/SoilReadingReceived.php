<?php

namespace App\Notifications;

use App\Models\Project;
use App\Models\SamplingPoint;
use App\Models\SoilReading;
use App\Services\SpeciesReference;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fires from SoilReadingService::record() the moment a field reading syncs
 * -- the only place in the app a researcher previously had no way to find
 * out this happened short of opening the project and looking. One
 * ->notify() call covers both channels the user asked for (in-app badge +
 * email) instead of two separate mechanisms.
 */
class SoilReadingReceived extends Notification
{
    public function __construct(
        private SoilReading $reading,
        private SamplingPoint $point,
        private Project $project,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $aoiName = $this->point->samplingGrid?->classificationRun?->orthomosaic?->areaOfInterest?->name ?? 'an area of interest';

        return (new MailMessage)
            ->subject('New soil reading synced — ' . $this->project->name)
            ->greeting('New field data is in.')
            ->line('Point #' . $this->point->priority_rank . ' in "' . $aoiName . '" (' . $this->project->name . ') was just collected by ' . ($this->reading->collector?->name ?? 'a field researcher') . '.')
            ->when($this->reading->species, fn ($mail) => $mail->line('Species: ' . SpeciesReference::label($this->reading->species)))
            ->action('View Soil Readings', route('projects.section', [$this->project->id, 'soil-readings']))
            ->line('This is an automated notice — no reply needed.');
    }

    public function toArray(object $notifiable): array
    {
        $aoiName = $this->point->samplingGrid?->classificationRun?->orthomosaic?->areaOfInterest?->name ?? 'an area of interest';

        return [
            'project_id' => $this->project->id,
            'project_name' => $this->project->name,
            'aoi_name' => $aoiName,
            'point_rank' => $this->point->priority_rank,
            'species' => $this->reading->species,
            'collector_name' => $this->reading->collector?->name,
            'url' => route('projects.section', [$this->project->id, 'soil-readings']),
        ];
    }
}
