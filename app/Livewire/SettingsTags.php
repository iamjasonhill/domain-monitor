<?php

namespace App\Livewire;

use App\Models\DomainTag;
use Livewire\Component;

class SettingsTags extends Component
{
    public $tags;

    public $showTagModal = false;

    public $editingTag = null;

    public $tagForm = [
        'name' => '',
        'priority' => 0,
        'color' => '#3B82F6',
        'description' => '',
    ];

    protected $rules = [
        'tagForm.name' => 'required|string|max:255|unique:domain_tags,name',
        'tagForm.priority' => 'required|integer|min:0',
        'tagForm.color' => 'nullable|string|max:7',
        'tagForm.description' => 'nullable|string',
    ];

    public function mount(): void
    {
        $this->loadTags();
    }

    public function loadTags(): void
    {
        $this->tags = DomainTag::orderedByPriority()->get();
    }

    public function openCreateModal(): void
    {
        $this->editingTag = null;
        $this->tagForm = [
            'name' => '',
            'priority' => 0,
            'color' => '#3B82F6',
            'description' => '',
        ];
        $this->showTagModal = true;
        $this->resetValidation();
    }

    public function openEditModal($tagId): void
    {
        $tag = DomainTag::findOrFail($tagId);
        $this->editingTag = $tag;
        $this->tagForm = [
            'name' => $tag->name,
            'priority' => $tag->priority,
            'color' => $tag->color ?? '#3B82F6',
            'description' => $tag->description ?? '',
        ];
        $this->showTagModal = true;
        $this->resetValidation();
    }

    public function closeModal(): void
    {
        $this->showTagModal = false;
        $this->editingTag = null;
        $this->tagForm = [
            'name' => '',
            'priority' => 0,
            'color' => '#3B82F6',
            'description' => '',
        ];
        $this->resetValidation();
    }

    public function saveTag(): void
    {
        if ($this->editingTag) {
            $this->rules['tagForm.name'] = 'required|string|max:255|unique:domain_tags,name,'.$this->editingTag->id;
        }

        $this->validate();

        if ($this->editingTag) {
            $this->editingTag->update([
                'name' => $this->tagForm['name'],
                'priority' => $this->tagForm['priority'],
                'color' => $this->tagForm['color'],
                'description' => $this->tagForm['description'],
            ]);
            session()->flash('message', 'Tag updated successfully!');
        } else {
            DomainTag::create([
                'name' => $this->tagForm['name'],
                'priority' => $this->tagForm['priority'],
                'color' => $this->tagForm['color'],
                'description' => $this->tagForm['description'],
            ]);
            session()->flash('message', 'Tag created successfully!');
        }

        $this->loadTags();
        $this->closeModal();
    }

    public function deleteTag($tagId): void
    {
        $tag = DomainTag::findOrFail($tagId);
        $tag->delete();
        session()->flash('message', 'Tag deleted successfully!');
        $this->loadTags();
    }

    public function updatePriority($tagId, $newPriority): void
    {
        $tag = DomainTag::findOrFail($tagId);
        $tag->update(['priority' => (int) $newPriority]);
        $this->loadTags();
    }

    public function render()
    {
        return view('livewire.settings-tags');
    }
}
