<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PrintAgent;
use App\Models\PrintJob;
use App\Models\PrintProfile;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    use LogsActivity;

    public function index()
    {
        $profiles = PrintProfile::with(['agent', 'branch.company'])->latest()->get();
        $agents = PrintAgent::where('is_active', true)->get();
        $branches = Branch::with('company')->active()->orderBy('name')->get();
        return view('admin.profiles', compact('profiles', 'agents', 'branches'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255|unique:print_profiles,name',
            'description'     => 'nullable|string|max:255',
            'branch_id'       => 'required|exists:branches,id',
            'paper_size'      => 'required|string',
            'is_custom'       => 'nullable',
            'custom_width'    => 'nullable|numeric|required_if:paper_size,CUSTOM',
            'custom_height'   => 'nullable|numeric|required_if:paper_size,CUSTOM',
            'margin_top'      => 'nullable|numeric',
            'margin_bottom'   => 'nullable|numeric',
            'margin_left'     => 'nullable|numeric',
            'margin_right'    => 'nullable|numeric',
            'orientation'     => 'required|string',
            'copies'          => 'required|integer|min:1',
            'duplex'          => 'required|string',
            'print_agent_id'  => 'required|exists:print_agents,id',
            'default_printer'   => 'required|string|max:255',
            'fit_to_page'       => 'nullable|boolean',
            'use_inches'        => 'nullable',
            'tray_source'       => 'nullable|string|in:auto,tray1,tray2,tray3,manual,envelope',
            'color_mode'        => 'nullable|string|in:color,monochrome',
            'print_quality'     => 'nullable|string|in:draft,normal,high',
            'scaling_percentage'=> 'nullable|integer|min:1|max:400',
            'media_type'        => 'nullable|string|in:plain,glossy,envelope,label,continuous_feed',
            'collate'           => 'nullable|boolean',
            'reverse_order'     => 'nullable|boolean',
            // Watermark fields
            'watermark_text'      => 'nullable|string|max:255',
            'watermark_opacity'   => 'nullable|numeric|min:0.1|max:1',
            'watermark_rotation'  => 'nullable|integer|min:-90|max:90',
            'watermark_position'  => 'nullable|string|in:center,tile,top-left,top-right,bottom-left,bottom-right',
            // Finishing fields
            'finishing_staple'  => 'nullable|string|in:single,dual,saddle',
            'finishing_punch'   => 'nullable|string|in:2,4',
            'finishing_booklet' => 'nullable|boolean',
            'finishing_fold'    => 'nullable|string|in:half,tri-fold,z-fold',
            'finishing_bind'    => 'nullable|string|in:tape,comb,thermal',
        ]);

        $data['is_custom'] = ($request->paper_size === 'CUSTOM');

        if ($data['is_custom'] && $request->has('use_inches')) {
            if (!empty($data['custom_width']))  $data['custom_width']  *= 25.4;
            if (!empty($data['custom_height'])) $data['custom_height'] *= 25.4;
        }

        $data['extra_options'] = [
            'fit_to_page' => $request->has('fit_to_page')
        ];

        unset($data['fit_to_page']);
        unset($data['use_inches']);

        PrintProfile::create($data);

        $this->logActivity('profile.created', null, ['name' => $data['name']]);

        return redirect()->route('admin.profiles')->with('success', 'Profile created!');
    }

    public function edit(PrintProfile $profile)
    {
        $agents = PrintAgent::where('is_active', true)->get();
        return view('admin.edit_profile', compact('profile', 'agents'));
    }

    public function update(Request $request, PrintProfile $profile)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255|unique:print_profiles,name,' . $profile->id,
            'description'     => 'nullable|string|max:255',
            'paper_size'      => 'required|string',
            'custom_width'    => 'nullable|numeric|required_if:paper_size,CUSTOM',
            'custom_height'   => 'nullable|numeric|required_if:paper_size,CUSTOM',
            'margin_top'      => 'nullable|numeric',
            'margin_bottom'   => 'nullable|numeric',
            'margin_left'     => 'nullable|numeric',
            'margin_right'    => 'nullable|numeric',
            'orientation'     => 'required|string',
            'copies'          => 'required|integer|min:1',
            'duplex'          => 'required|string',
            'print_agent_id'  => 'required|exists:print_agents,id',
            'default_printer'   => 'required|string|max:255',
            'fit_to_page'       => 'nullable|boolean',
            'use_inches'        => 'nullable',
            'tray_source'       => 'nullable|string|in:auto,tray1,tray2,tray3,manual,envelope',
            'color_mode'        => 'nullable|string|in:color,monochrome',
            'print_quality'     => 'nullable|string|in:draft,normal,high',
            'scaling_percentage'=> 'nullable|integer|min:1|max:400',
            'media_type'        => 'nullable|string|in:plain,glossy,envelope,label,continuous_feed',
            'collate'           => 'nullable|boolean',
            'reverse_order'     => 'nullable|boolean',
            // Watermark fields
            'watermark_text'      => 'nullable|string|max:255',
            'watermark_opacity'   => 'nullable|numeric|min:0.1|max:1',
            'watermark_rotation'  => 'nullable|integer|min:-90|max:90',
            'watermark_position'  => 'nullable|string|in:center,tile,top-left,top-right,bottom-left,bottom-right',
            // Finishing fields
            'finishing_staple'  => 'nullable|string|in:single,dual,saddle',
            'finishing_punch'   => 'nullable|string|in:2,4',
            'finishing_booklet' => 'nullable|boolean',
            'finishing_fold'    => 'nullable|string|in:half,tri-fold,z-fold',
            'finishing_bind'    => 'nullable|string|in:tape,comb,thermal',
        ]);

        $data['is_custom'] = ($request->paper_size === 'CUSTOM');

        if ($data['is_custom'] && $request->has('use_inches')) {
            if (!empty($data['custom_width']))  $data['custom_width']  *= 25.4;
            if (!empty($data['custom_height'])) $data['custom_height'] *= 25.4;
        }

        $data['extra_options'] = [
            'fit_to_page' => $request->has('fit_to_page')
        ];

        unset($data['fit_to_page']);
        unset($data['use_inches']);

        $profile->update($data);
        return redirect()->route('admin.profiles')->with('success', 'Profile updated!');
    }

    public function destroy(PrintProfile $profile)
    {
        $this->logActivity('profile.deleted', $profile, ['name' => $profile->name]);
        $profile->delete();
        return redirect()->route('admin.profiles')->with('success', 'Profile removed.');
    }

    public function testPrint(Request $request, PrintProfile $profile)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:10240',
        ]);

        $agent = $profile->agent;
        if (!$agent) {
            $agent = PrintAgent::where('is_active', true)->get()->first(fn($a) => $a->isOnline());
        }

        if (!$agent) {
            return redirect()->back()->with('error', 'No online agent available to process the test print.');
        }

        $jobId = (string) Str::uuid();
        $path = $request->file('file')->storeAs('print_jobs', "{$jobId}.pdf", 'local');

        PrintJob::create([
            'job_id'          => $jobId,
            'print_agent_id'  => $agent->id,
            'printer_name'    => $profile->default_printer ?: 'Default',
            'type'            => 'pdf',
            'status'          => 'pending',
            'file_path'       => $path,
            'options'         => [
                'orientation'    => $profile->orientation,
                'paper_size'     => $profile->paper_size,
                'paper_width_mm' => $profile->custom_width,
                'paper_height_mm'=> $profile->custom_height,
                'margin_top'     => $profile->margin_top,
                'margin_bottom'  => $profile->margin_bottom,
                'margin_left'    => $profile->margin_left,
                'margin_right'   => $profile->margin_right,
                'fit_to_page'    => $profile->extra_options['fit_to_page'] ?? false,
                'copies'         => 1,
            ],
        ]);

        return redirect()->back()->with('success', "Test print job created! ID: {$jobId}. Monitor its status on the Dashboard.");
    }
}
