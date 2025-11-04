<?php

namespace Features\Tenant\Shared\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationSettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'date_format' => $this->date_format,
            'time_format' => $this->time_format,
            'currency' => $this->currency,
            'notification_preferences' => $this->notification_preferences,
            'feature_flags' => $this->feature_flags,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
