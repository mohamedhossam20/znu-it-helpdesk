<?php

namespace App\Http\Requests;

use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $attachments = config('helpdesk.attachments');

        return [
            // "admin" is only for tickets the IT team logs from the panel.
            'source' => ['required', Rule::enum(TicketSource::class)->except([TicketSource::Admin])],
            'room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')->whereNull('deleted_at'), 'required_unless:source,my_device'],
            'asset_id' => ['nullable', 'integer', Rule::exists('assets', 'id')->whereNull('deleted_at')],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            // Urgent is reserved for "a class is waiting right now" and for IT staff.
            'priority' => ['nullable', Rule::enum(TicketPriority::class)->except([TicketPriority::Urgent])],
            'is_class_in_progress' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
            'photos' => ['sometimes', 'array', 'max:'.$attachments['max_files']],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp,heic', 'max:'.$attachments['max_kilobytes']],
        ];
    }
}
