<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Models\Advertisement;
use App\Models\AuditLog;

/**
 * Admin management of advertisement banners.
 * AdminController already exceeds 1500 lines, so this is a satellite controller.
 */
class AdminAdsController extends Controller
{
    public function __construct()
    {
        Auth::requireAdmin();
    }

    public function index(): void
    {
        $ads = Advertisement::findAll();
        $this->render('admin/advertisements', ['ads' => $ads]);
    }

    public function create(): void
    {
        $this->render('admin/advertisement_form', [
            'ad'    => null,
            'title' => 'Nowa reklama',
        ]);
    }

    public function store(): void
    {
        if (!$this->validateCsrf()) {
            $this->redirect('/admin/advertisements');
            return;
        }

        $data = $this->collectFormData();
        if (!$data) {
            Session::flash('error', 'Wypełnij wymagane pola (tytuł, treść, placement).');
            $this->redirect('/admin/advertisements/create');
            return;
        }

        $data['created_by'] = Auth::currentUserId();
        $id = Advertisement::create($data);

        AuditLog::log('admin', Auth::currentUserId(), 'ad_create',
            json_encode(['id' => $id, 'placement' => $data['placement'], 'title' => $data['title']]),
            'advertisement', $id);

        Session::flash('success', 'Reklama została dodana.');
        $this->redirect('/admin/advertisements');
    }

    public function edit(string $id): void
    {
        $ad = Advertisement::findById((int) $id);
        if (!$ad) {
            $this->notFound();
        }

        $this->render('admin/advertisement_form', [
            'ad'    => $ad,
            'title' => 'Edytuj reklamę',
        ]);
    }

    public function update(string $id): void
    {
        $id = (int) $id;
        if (!$this->validateCsrf()) {
            $this->redirect("/admin/advertisements/{$id}/edit");
            return;
        }

        $ad = Advertisement::findById($id);
        if (!$ad) {
            $this->notFound();
        }

        $data = $this->collectFormData();
        if (!$data) {
            Session::flash('error', 'Wypełnij wymagane pola (tytuł, treść, placement).');
            $this->redirect("/admin/advertisements/{$id}/edit");
            return;
        }

        Advertisement::update($id, $data);

        AuditLog::log('admin', Auth::currentUserId(), 'ad_update',
            json_encode(['id' => $id, 'placement' => $data['placement']]),
            'advertisement', $id);

        Session::flash('success', 'Reklama została zaktualizowana.');
        $this->redirect('/admin/advertisements');
    }

    public function delete(string $id): void
    {
        $id = (int) $id;
        if (!$this->validateCsrf()) {
            $this->redirect('/admin/advertisements');
            return;
        }

        $ad = Advertisement::findById($id);
        if (!$ad) {
            $this->notFound();
        }

        Advertisement::delete($id);

        AuditLog::log('admin', Auth::currentUserId(), 'ad_delete',
            json_encode(['id' => $id, 'title' => $ad['title']]),
            'advertisement', $id);

        Session::flash('success', 'Reklama została usunięta.');
        $this->redirect('/admin/advertisements');
    }

    public function toggle(string $id): void
    {
        $id = (int) $id;
        if (!$this->validateCsrf()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'csrf']);
            return;
        }

        $ad = Advertisement::findById($id);
        if (!$ad) {
            http_response_code(404);
            echo json_encode(['ok' => false]);
            return;
        }

        Advertisement::toggleActive($id);
        $newState = !(bool) $ad['is_active'];

        AuditLog::log('admin', Auth::currentUserId(), 'ad_toggle',
            json_encode(['id' => $id, 'is_active' => $newState]),
            'advertisement', $id);

        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'is_active' => $newState]);
    }

    private function collectFormData(): ?array
    {
        $title     = trim($_POST['title'] ?? '');
        $content   = trim($_POST['content'] ?? '');
        $placement = $_POST['placement'] ?? '';

        if (!$title || !$content || !array_key_exists($placement, Advertisement::PLACEMENTS)) {
            return null;
        }

        $type = $_POST['type'] ?? 'info';
        if (!array_key_exists($type, Advertisement::TYPES)) {
            $type = 'info';
        }

        $linkUrl  = trim($_POST['link_url'] ?? '') ?: null;
        $linkText = trim($_POST['link_text'] ?? '') ?: null;
        $sortOrder = max(0, (int) ($_POST['sort_order'] ?? 0));

        $startsAt = $this->parseDateTime($_POST['starts_at'] ?? '');
        $endsAt   = $this->parseDateTime($_POST['ends_at'] ?? '');

        return [
            'placement'  => $placement,
            'title'      => $title,
            'content'    => $content,
            'link_url'   => $linkUrl,
            'link_text'  => $linkText,
            'type'       => $type,
            'is_active'  => isset($_POST['is_active']) ? 1 : 0,
            'starts_at'  => $startsAt,
            'ends_at'    => $endsAt,
            'sort_order' => $sortOrder,
        ];
    }

    private function parseDateTime(string $value): ?string
    {
        if (!$value) return null;
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?$/', $value)) {
            $dt = \DateTime::createFromFormat(strlen($value) === 10 ? 'Y-m-d' : 'Y-m-d H:i', substr($value, 0, 16));
            return $dt ? $dt->format('Y-m-d H:i:s') : null;
        }
        return null;
    }
}
