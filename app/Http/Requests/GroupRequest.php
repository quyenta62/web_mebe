<?php

namespace App\Http\Requests;

use App\Models\FacebookGroup;
use App\Support\FacebookGroupUrl;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create and update a group. The Facebook Group ID can only be set on create:
 * posts and crawl history are tied to it.
 */
class GroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Access is enforced by the admin middleware on the routes.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name'));

        $this->merge([
            'facebook_group_id' => trim((string) $this->input('facebook_group_id')),
            'url' => trim((string) $this->input('url')),
            'name' => $name === '' ? null : $name,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        $rules = [
            'url' => ['required', 'string', 'max:500', function (string $attribute, mixed $value, Closure $fail) {
                if (FacebookGroupUrl::groupKey((string) $value) === null) {
                    $fail('URL phải có dạng https://www.facebook.com/groups/<id-hoặc-tên-group>/');
                }
            }],
            'name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];

        if ($this->group() === null) {
            $rules['facebook_group_id'] = [
                'required',
                'regex:/^\d{5,25}$/',
                // A soft-deleted group with the same ID is restored instead (see GroupController@store).
                Rule::unique('facebook_groups', 'facebook_group_id')->whereNull('deleted_at'),
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'facebook_group_id.regex' => 'Facebook Group ID phải là dãy số (5–25 chữ số).',
            'facebook_group_id.unique' => 'Group này đã có trong danh sách.',
        ];
    }

    public function attributes(): array
    {
        return [
            'facebook_group_id' => 'Facebook Group ID',
            'url' => 'Group URL',
            'name' => 'Group Name',
        ];
    }

    /** A numeric group ID inside the URL must be the same group. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['url', 'facebook_group_id'])) {
                    return;
                }
                $key = FacebookGroupUrl::groupKey($this->input('url'));
                $groupId = $this->group()?->facebook_group_id ?? $this->input('facebook_group_id');
                if ($key !== null && ctype_digit($key) && $key !== $groupId) {
                    $validator->errors()->add('url', "URL trỏ tới group {$key}, không khớp Facebook Group ID {$groupId}.");
                }
            },
        ];
    }

    /** Validated attributes with the URL normalised to https://www.facebook.com/groups/<key>/ */
    public function groupData(): array
    {
        $data = $this->validated();
        $data['url'] = FacebookGroupUrl::canonical(FacebookGroupUrl::groupKey($data['url']));

        return $data;
    }

    private function group(): ?FacebookGroup
    {
        return $this->route('group');
    }
}
