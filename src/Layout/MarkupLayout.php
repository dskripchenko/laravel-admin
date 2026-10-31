<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Layout;

use Dskripchenko\LaravelAdmin\Action\Button;
use Dskripchenko\LaravelAdmin\Permission\PermissionCheck;

/**
 * A document of the UI kit kept as markup — a kanban board or an event
 * calendar — that the user may edit on the screen.
 *
 *     Layout::kanban('board')
 *         ->editable('saveBoard', permission: 'tasks.edit')
 *         ->lockable('lockBoard', permission: 'tasks.lock')
 *         ->locked('board_locked')
 *
 * The methods are the screen's own public methods, called through its
 * `runMethod` action — the same session, CSRF, layers and permissions as a
 * button. Each one this layout names is declared for the server: a user who
 * cannot see the layout (canSee) cannot call them, and a method with a
 * permission needs it too. Without the permission the board is read-only.
 *
 *   - editable: `(string $markup)` — the whole new markup after each change;
 *               throw ActionFailedException (or return a non-2xx response)
 *               to roll the change back, the message is shown in a toast.
 *   - lockable: `(bool $locked)` — the lock button was pressed.
 */
abstract class MarkupLayout extends DocumentLayout
{
    private ?string $saveMethod = null;

    private ?string $savePermission = null;

    private ?string $lockMethod = null;

    private ?string $lockPermission = null;

    private bool $locked = false;

    private ?string $lockedSource = null;

    /**
     * Lets the user edit the document; each change is sent to a screen method
     * as the new markup. With $permission the editing is offered — and the
     * method may be called — only to a user holding it.
     */
    public function editable(string $method, ?string $permission = null): static
    {
        $this->saveMethod = $method;
        $this->savePermission = $permission;

        return $this;
    }

    /**
     * Shows the lock button; pressing it calls a screen method with the new
     * state, `true` for locked. With $permission the button is shown — and
     * the method may be called — only to a user holding it.
     */
    public function lockable(string $method, ?string $permission = null): static
    {
        $this->lockMethod = $method;
        $this->lockPermission = $permission;

        return $this;
    }

    /**
     * Read-only, whatever editable() says: a flag, or the state key that
     * holds it.
     */
    public function locked(bool|string $locked = true): static
    {
        if (is_string($locked)) {
            $this->lockedSource = $locked;
            $this->locked = false;
        } else {
            $this->locked = $locked;
            $this->lockedSource = null;
        }

        return $this;
    }

    /**
     * The screen methods this layout calls, declared for the server's check
     * (ActionLocator): runMethod finds them here, so the layout's visibility
     * and the permissions guard them like a button's.
     *
     * @return list<\Dskripchenko\LaravelAdmin\Contracts\Renderable>
     */
    public function childRenderables(): array
    {
        $declared = [];
        foreach ([
            'save' => [$this->saveMethod, $this->savePermission],
            'lock' => [$this->lockMethod, $this->lockPermission],
        ] as $role => [$method, $permission]) {
            if ($method === null) {
                continue;
            }
            $button = Button::make($this->type())->withName($this->type().'-'.$role)->method($method);
            if ($permission !== null) {
                $button->permission($permission);
            }
            $declared[] = $button;
        }

        return $declared;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        // No editing and no lock for a user who may not use them.
        $canSave = $this->saveMethod !== null
            && ($this->savePermission === null || PermissionCheck::allows($this->savePermission));
        $canLock = $this->lockMethod !== null
            && ($this->lockPermission === null || PermissionCheck::allows($this->lockPermission));

        $array['editable'] = $canSave;
        $array['saveMethod'] = $canSave ? $this->saveMethod : null;
        $array['lockable'] = $canLock;
        $array['lockMethod'] = $canLock ? $this->lockMethod : null;
        $array['locked'] = $this->locked;
        $array['lockedSource'] = $this->lockedSource;

        return $array;
    }
}
