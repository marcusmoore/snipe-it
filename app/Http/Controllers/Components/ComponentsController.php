<?php

namespace App\Http\Controllers\Components;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImageUploadRequest;
use App\Models\Company;
use App\Models\Component;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use League\Csv\EscapeFormula;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * This class controls all actions related to Components for
 * the Snipe-IT Asset Management application.
 *
 * @version    v1.0
 */
class ComponentsController extends Controller
{
    /**
     * Returns a view that invokes the ajax tables which actually contains
     * the content for the components listing, which is generated in getDatatable.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     *
     * @see ComponentsController::getDatatable() method that generates the JSON response
     * @since [v3.0]
     *
     * @return View
     *
     * @throws AuthorizationException
     */
    public function index()
    {
        $this->authorize('view', Component::class);

        return view('components/index');
    }

    /**
     * Returns a form to create a new component.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     *
     * @see ComponentsController::postCreate() method that stores the data
     * @since [v3.0]
     *
     * @return View
     *
     * @throws AuthorizationException
     */
    public function create()
    {
        $this->authorize('create', Component::class);

        return view('components/edit')->with('category_type', 'component')
            ->with('item', new Component);
    }

    /**
     * Validate and store data for new component.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     *
     * @see ComponentsController::getCreate() method that generates the view
     * @since [v3.0]
     *
     * @return RedirectResponse
     *
     * @throws AuthorizationException
     */
    public function store(ImageUploadRequest $request)
    {
        $this->authorize('create', Component::class);
        $component = new Component;
        $component->name = $request->input('name');
        $component->category_id = $request->input('category_id');
        $component->supplier_id = $request->input('supplier_id');
        $component->manufacturer_id = $request->input('manufacturer_id');
        $component->model_number = $request->input('model_number');
        $component->location_id = $request->input('location_id');
        $component->company_id = Company::getIdForCurrentUser($request->input('company_id'));
        $component->order_number = $request->input('order_number', null);
        $component->min_amt = $request->input('min_amt', null);
        $component->serial = $request->input('serial', null);
        $component->purchase_date = $request->input('purchase_date', null);
        $component->purchase_cost = $request->input('purchase_cost', null);
        $component->qty = $request->input('qty');
        $component->created_by = auth()->id();
        $component->notes = $request->input('notes');

        $component = $request->handleImages($component);

        if ($request->input('redirect_option') === 'back') {
            session()->put(['redirect_option' => 'index']);
        } else {
            session()->put(['redirect_option' => $request->input('redirect_option')]);
        }

        if ($component->save()) {
            return Helper::getRedirectOption($request, $component->id, 'Components')
                ->with('success', trans('admin/components/message.create.success'));
        }

        return redirect()->back()->withInput()->withErrors($component->getErrors());
    }

    /**
     * Return a view to edit a component.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     *
     * @see ComponentsController::postEdit() method that stores the data.
     * @since [v3.0]
     *
     * @param  int  $componentId
     * @return View
     *
     * @throws AuthorizationException
     */
    public function edit(Component $component)
    {

        $this->authorize('update', $component);
        session()->put('url.intended', url()->previous());

        return view('components/edit')
            ->with('item', $component)
            ->with('category_type', 'component');
    }

    /**
     * Return a view to edit a component.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     *
     * @see ComponentsController::getEdit() method presents the form.
     *
     * @param  int  $componentId
     * @return RedirectResponse
     *
     * @throws AuthorizationException
     *
     * @since [v3.0]
     */
    public function update(ImageUploadRequest $request, Component $component)
    {
        $min = $component->numCheckedOut();
        $validator = Validator::make($request->all(), [
            'qty' => "required|numeric|min:$min",
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $this->authorize('update', $component);

        // Update the component data
        $component->name = $request->input('name');
        $component->category_id = $request->input('category_id');
        $component->supplier_id = $request->input('supplier_id');
        $component->manufacturer_id = $request->input('manufacturer_id');
        $component->model_number = $request->input('model_number');
        $component->location_id = $request->input('location_id');
        $component->company_id = Company::getIdForCurrentUser($request->input('company_id'));
        $component->order_number = $request->input('order_number');
        $component->min_amt = $request->input('min_amt');
        $component->serial = $request->input('serial');
        $component->purchase_date = $request->input('purchase_date');
        $component->purchase_cost = request('purchase_cost');
        $component->qty = $request->input('qty');
        $component->notes = $request->input('notes');

        $component = $request->handleImages($component);

        session()->put(['redirect_option' => $request->input('redirect_option')]);

        if ($component->save()) {
            return Helper::getRedirectOption($request, $component->id, 'Components')
                ->with('success', trans('admin/components/message.update.success'));
        }

        return redirect()->back()->withInput()->withErrors($component->getErrors());
    }

    /**
     * Delete a component.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     *
     * @since [v3.0]
     *
     * @param  int  $componentId
     * @return RedirectResponse
     *
     * @throws AuthorizationException
     */
    public function destroy($componentId)
    {
        if (is_null($component = Component::find($componentId))) {
            return redirect()->route('components.index')->with('error', trans('admin/components/message.does_not_exist'));
        }

        $this->authorize('delete', $component);

        // Remove the image if one exists
        if ($component->image && Storage::disk('public')->exists('components/'.$component->image)) {
            try {
                Storage::disk('public')->delete('components/'.$component->image);
            } catch (\Exception $e) {
                Log::debug($e);
            }
        }

        if ($component->numCheckedOut() > 0) {
            return redirect()->route('components.index')->with('error', trans('admin/components/message.delete.error_qty'));
        }

        $component->delete();

        return redirect()->route('components.index')->with('success', trans('admin/components/message.delete.success'));
    }

    /**
     * Return a view to display component information.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     *
     * @see ComponentsController::getDataView() method that generates the JSON response
     * @since [v3.0]
     *
     * @param  int  $componentId
     * @return View
     *
     * @throws AuthorizationException
     */
    public function show(Component $component)
    {
        $this->authorize('view', $component);

        return view('components/view', compact('component'))->with('snipe_component', $component);
    }

    public function getClone(Component $component): View|RedirectResponse
    {
        $this->authorize('create', Component::class);

        $cloned_component = clone $component;
        $cloned_component->id = null;
        $cloned_component->deleted_at = null;

        // Show the page
        return view('components/edit')
            ->with('item', $cloned_component)
            ->with('component', $cloned_component);
    }

    public function getExportComponentCsv()
    {
        $this->authorize('view', Component::class);

        $this->disableDebugbar();

        return new StreamedResponse(function () {
            // Open output stream
            $handle = fopen('php://output', 'w');

            $headers = [
                // strtolower to prevent Excel from trying to open it as a SYLK file
                strtolower(trans('general.id')),
                trans('general.company'),
                trans('general.name'),
                trans('admin/hardware/form.serial'),
                trans('general.category'),
                trans('general.supplier'),
                trans('admin/models/table.modelnumber'),
                trans('general.manufacturer'),
                trans('general.location'),
                trans('general.order_number'),
                trans('general.purchase_date'),
                trans('general.min_amt'),
                trans('admin/components/general.total'),
                trans('admin/components/general.remaining'),
                trans('general.unit_cost'),
                trans('general.total_cost'),
                trans('general.notes'),
                trans('general.created_at'),
                trans('general.updated_at'),
            ];

            fputcsv($handle, $headers);

            Component::with([])->orderBy('created_at', 'DESC')
                ->chunk(500, function ($components) use ($handle) {

                    $formatter = new EscapeFormula('`');

                    foreach ($components as $component) {
                        // Add a new row with data
                        $values = [
                            $component->id,
                            $component?->company?->name,
                            $component->name,
                            $component->serial,
                            $component?->category?->name,
                            $component?->supplier?->name,
                            $component->model_number,
                            $component?->manufacturer?->name,
                            $component?->location?->name,
                            $component->order_number,
                            $component->purchase_date ? Carbon::make($component->purchase_date)->format('Y-m-d') : '',
                            $component->min_amt,
                            $component->qty,
                            (int) $component->numRemaining(),
                            $component->purchase_cost,
                            Helper::formatCurrencyOutput($component->totalCostSum()),
                            $component->notes,
                            $component->created_at,
                            $component->updated_at,
                        ];

                        // CSV_ESCAPE_FORMULAS is set to false in the .env
                        if (config('app.escape_formulas') === false) {
                            fputcsv($handle, $values);

                            // CSV_ESCAPE_FORMULAS is set to true or is not set in the .env
                        } else {
                            fputcsv($handle, $formatter->escapeRecord($values));
                        }
                    }
                });
            // Close the output stream
            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="components-'.date('Y-m-d-his').'.csv"',
        ]);

    }
}
