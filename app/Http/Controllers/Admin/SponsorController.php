<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Sponsor;
use App\Models\SponsorInquiry;
use App\Services\Audit;
use Illuminate\Http\Request;

/** SSC manages the landing-page sponsors and reads the enquiries companies send. */
class SponsorController extends Controller
{
    private const LOGO_DIR = 'uploads/sponsors';

    public function index()
    {
        return view('admin.sponsors', [
            'sponsors'  => Sponsor::orderBy('sort')->orderBy('id')->get(),
            'inquiries' => SponsorInquiry::orderByRaw("status = 'new' DESC")->orderByDesc('id')->limit(100)->get(),
            'published' => Setting::enabled('show_sponsors'),
        ]);
    }

    public function store(Request $request)
    {
        $name    = trim(preg_replace('/\s+/', ' ', (string) $request->input('name')) ?? '');
        $website = trim((string) $request->input('website'));
        $bad     = fn (string $m) => back()->withInput()->with('flash', flash('error', 'Could not add the sponsor', e($m)));

        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            return $bad('Enter the sponsor’s name.');
        }
        if ($website !== '' && !preg_match('#^https?://[^\s]+$#i', $website)) {
            return $bad('The website must start with http:// or https://.');
        }

        $logo = null;
        if ($file = $request->file('logo')) {
            // Judge the bytes, not the file name or the type the browser sent. SVG is refused: it can carry scripts.
            $mime = $file->isValid() ? (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()) : '';
            $ext  = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$mime] ?? null;
            if ($ext === null || $file->getSize() > 2 * 1024 * 1024 || !@getimagesize($file->getRealPath())) {
                return $bad('The logo must be a PNG, JPG or WebP picture under 2 MB.');
            }
            $dir = public_path(self::LOGO_DIR);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $fileName = bin2hex(random_bytes(12)) . '.' . $ext;
            $file->move($dir, $fileName);
            $logo = self::LOGO_DIR . '/' . $fileName;
        }

        $s = Sponsor::create([
            'name' => $name, 'logo_path' => $logo, 'website' => $website === '' ? null : $website,
            'sort' => (int) Sponsor::max('sort') + 1,
        ]);
        Audit::log('sponsor.add', 'sponsor', (string) $s->id, ['name' => $name]);

        return back()->with('flash', flash('success', 'Sponsor added', e($name) . (Setting::enabled('show_sponsors') ? ' now shows on the landing page.' : ' is saved. It shows once you publish the sponsors section in Site Settings.')));
    }

    public function toggle(Sponsor $sponsor)
    {
        $sponsor->update(['active' => !$sponsor->active]);
        Audit::log('sponsor.toggle', 'sponsor', (string) $sponsor->id, ['active' => $sponsor->active]);

        return back()->with('flash', flash('success', $sponsor->active ? 'Shown' : 'Hidden', e($sponsor->name) . ($sponsor->active ? ' is on the landing page.' : ' is hidden from the landing page.')));
    }

    public function destroy(Sponsor $sponsor)
    {
        if ($sponsor->logo_path && str_starts_with($sponsor->logo_path, self::LOGO_DIR . '/')) {
            @unlink(public_path($sponsor->logo_path));
        }
        Audit::log('sponsor.remove', 'sponsor', (string) $sponsor->id, ['name' => $sponsor->name]);
        $sponsor->delete();

        return back()->with('flash', flash('success', 'Sponsor removed', e($sponsor->name) . ' was taken off the landing page.'));
    }

    public function inquiry(Request $request, SponsorInquiry $inquiry)
    {
        $status = (string) $request->input('status');
        abort_unless(in_array($status, ['new', 'contacted', 'closed'], true), 422);

        $inquiry->update(['status' => $status]);
        Audit::log('sponsor.inquiry.' . $status, 'sponsor', (string) $inquiry->id);

        return back()->with('flash', flash('success', 'Updated', e($inquiry->company) . ' is marked ' . $status . '.'));
    }
}
