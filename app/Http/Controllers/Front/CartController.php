<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Common\TemplateController;
use App\Http\Controllers\Controller;
use App\Model\Payment\Currency;
use App\Model\Payment\PlanPrice;
use App\Model\Payment\Tax;
use App\Model\Payment\TaxOption;
use App\Model\Product\Product;
use Cart;
use Illuminate\Http\Request;
use Session;

class CartController extends Controller
{
    public $templateController;
    public $product;
    public $currency;
    public $addons;
    public $addonRelation;
    public $licence;
    public $tax_option;

    public function __construct()
    {
        $templateController = new TemplateController();
        $this->templateController = $templateController;

        $product = new Product();
        $this->product = $product;

        $plan_price = new PlanPrice();
        $this->$plan_price = $plan_price;

        $currency = new Currency();
        $this->currency = $currency;

        $tax = new Tax();
        $this->tax = $tax;

        $tax_option = new TaxOption();
        $this->tax_option = $tax_option;
    }

    public function productList(Request $request)
    {
        try {
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) {   //check ip from share internet
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {   //to check ip is pass from proxy
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } else {
                $ip = $_SERVER['REMOTE_ADDR'];
            }

            if ($ip != '::1') {
                $location = json_decode(file_get_contents('http://ip-api.com/json/'.$ip), true);
            } else {
                $location = json_decode(file_get_contents('http://ip-api.com/json'), true);
            }
        } catch (\Exception $ex) {
            $location = false;
            $error = $ex->getMessage();
        }

        $country = \App\Http\Controllers\Front\CartController::findCountryByGeoip($location['countryCode']);
        $states = \App\Http\Controllers\Front\CartController::findStateByRegionId($location['countryCode']);
        $states = \App\Model\Common\State::pluck('state_subdivision_name', 'state_subdivision_code')->toArray();
        $state_code = $location['countryCode'].'-'.$location['region'];
        $state = \App\Http\Controllers\Front\CartController::getStateByCode($state_code);
        $mobile_code = \App\Http\Controllers\Front\CartController::getMobileCodeByIso($location['countryCode']);

        if ($location['country'] == 'India') {
            $currency = 'INR';
        } else {
            $currency = 'USD';
        }
        if (\Auth::user()) {
            $currency = 'INR';
            $user_currency = \Auth::user()->currency;
            if ($user_currency == 1 || $user_currency == 'USD') {
                $currency = 'USD';
            }
        }
        \Session::put('currency', $currency);
        if (!\Session::has('currency')) {
            \Session::put('currency', 'INR');
            //dd(\Session::get('currency'));
        }

        try {
            $page_controller = new PageController();

            return $page_controller->cart();
        } catch (\Exception $ex) {
            return redirect()->back()->with('fails', $ex->getMessage());
        }
    }

    public function cart(Request $request)
    {
        try {
            $plan = '';

            if ($request->has('subscription')) {
                $plan = $request->get('subscription');

                Session::put('plan', $plan);
            }
            $id = $request->input('id');

            if (!array_key_exists($id, Cart::getContent())) {
                $items = $this->addProduct($id);

                Cart::add($items);
            }

            return redirect('show/cart');
        } catch (\Exception $ex) {
            // dd($ex);
            return redirect()->back()->with('fails', $ex->getMessage());
        }
    }

    public function showCart()
    {
        try {
            $currency = 'INR';
            $cart_currency = 'INR';
            $attributes = [];
            $cartCollection = Cart::getContent();
            foreach ($cartCollection as $item) {
                $attributes[] = $item->attributes;
                $cart_currency = $attributes[0]['currency'];
                $currency = $attributes[0]['currency'];
                if (\Auth::user()) {
                    $cart_currency = $attributes[0]['currency'];
                    $user_currency = \Auth::user()->currency;
                    $currency = 'INR';
                    if ($user_currency == 1 || $user_currency == 'USD') {
                        $currency = 'USD';
                    }
                    if ($cart_currency != $currency) {
                        $id = $item->id;
                        Cart::remove($id);
                        $items = $this->addProduct($id);

                        Cart::add($items);
                        //
                    }
                }
            }
            // if ($cart_currency != $currency) {
            //     return redirect('show/cart');
            // }
            //dd(Cart::getContent());

            return view('themes.default1.front.cart', compact('cartCollection', 'attributes'));
        } catch (\Exception $ex) {
            //dd($ex);

            return redirect()->back()->with('fails', $ex->getMessage());
        }
    }

    public function checkTax($productid)
    {
        try {
            $tax_attribute[0] = ['name' => 'null', 'rate' => 0];
            $taxCondition[0] = new \Darryldecode\Cart\CartCondition([
                'name'   => 'null',
                'type'   => 'tax',
                'target' => 'item',
                'value'  => '0%',
            ]);

            $product = $this->product->findOrFail($productid);
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) {   //check ip from share internet
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {   //to check ip is pass from proxy
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } else {
                $ip = $_SERVER['REMOTE_ADDR'];
            }

            if ($ip != '::1') {
                $location = json_decode(file_get_contents('http://ip-api.com/json/'.$ip), true);
            } else {
                $location = json_decode(file_get_contents('http://ip-api.com/json'), true);
            }

            $country = \App\Http\Controllers\Front\CartController::findCountryByGeoip($location['countryCode']);
            $states = \App\Http\Controllers\Front\CartController::findStateByRegionId($location['countryCode']);
            $states = \App\Model\Common\State::pluck('state_subdivision_name', 'state_subdivision_code')->toArray();
            $state_code = $location['countryCode'].'-'.$location['region'];
            $state = \App\Http\Controllers\Front\CartController::getStateByCode($state_code);
            $mobile_code = \App\Http\Controllers\Front\CartController::getMobileCodeByIso($location['countryCode']);
            $country_iso = $location['countryCode'];
            // $state_code = $location['isoCode'].'-'.$location['state'];
            $geoip_country = '';
            $geoip_state = '';
            if (\Auth::user()) {
                $geoip_country = \Auth::user()->country;
                $geoip_state = \Auth::user()->state;
            }
            if ($geoip_country == '') {
                $geoip_country = \App\Http\Controllers\Front\CartController::findCountryByGeoip($country_iso);
            }
            $geoip_state_array = \App\Http\Controllers\Front\CartController::getStateByCode($state_code);
            if ($geoip_state == '') {
                if (array_key_exists('id', $geoip_state_array)) {
                    $geoip_state = $geoip_state_array['id'];
                }
            }

            if ($this->tax_option->findOrFail(1)->inclusive == 0) {
                $tax_rule = $this->tax_option->findOrFail(1);
                $product1 = $tax_rule->inclusive;
                $shop = $tax_rule->shop_inclusive;
                $cart = $tax_rule->cart_inclusive;
                if ($product->tax()->first()) {
                    $tax_class_id = $product->tax()->first()->tax_class_id;
                    if ($this->tax_option->findOrFail(1)->tax_enable == 1) {
                        if ($product1 == 0) {
                            $taxes = $this->getTaxByPriority($tax_class_id);
                            $rate = 0;
                            foreach ($taxes as $key => $tax) {
                                if ($tax->country == $geoip_country || $tax->state == $geoip_state || ($tax->country == '' && $tax->state == '')) {
                                    if ($tax->compound == 1) {
                                        $tax_attribute[$key] = ['name' => $tax->name, 'rate' => $tax->rate];
                                        $taxCondition[$key] = new \Darryldecode\Cart\CartCondition([
                                            'name'   => $tax->name,
                                            'type'   => 'tax',
                                            'target' => 'item',
                                            'value'  => $tax->rate.'%',
                                        ]);
                                    } else {
                                        $tax_attribute[$key] = ['name' => $tax->name, 'rate' => $tax->rate];
                                        $rate += $tax->rate;
                                        $taxCondition[0] = new \Darryldecode\Cart\CartCondition([
                                            'name'   => 'no compound',
                                            'type'   => 'tax',
                                            'target' => 'item',
                                            'value'  => $rate.'%',
                                        ]);
                                    }
                                }
                            }
                        } else {
                            if ($product->tax()->first()) {
                                $tax_class_id = $product->tax()->first()->tax_class_id;
                                if ($this->tax_option->findOrFail(1)->tax_enable == 1) {
                                    $taxes = $this->getTaxByPriority($tax_class_id);
                                    foreach ($taxes as $key => $tax) {
                                        $tax_attribute[$key] = ['name' => $tax->name, 'rate' => $tax->rate];
                                    }
                                }
                            }
                        }
                    } else {
                        if ($product->tax()->first()) {
                            $tax_class_id = $product->tax()->first()->tax_class_id;
                            if ($this->tax_option->findOrFail(1)->tax_enable == 1) {
                                $taxes = $this->getTaxByPriority($tax_class_id);
                                foreach ($taxes as $key => $tax) {
                                    $tax_attribute[$key] = ['name' => $tax->name, 'rate' => $tax->rate];
                                }
                            }
                        }
                    }
                }
            } else {
                if ($product->tax()->first()) {
                    $tax_class_id = $product->tax()->first()->tax_class_id;
                    if ($this->tax_option->findOrFail(1)->tax_enable == 1) {
                        $taxes = $this->getTaxByPriority($tax_class_id);
                        foreach ($taxes as $key => $tax) {
                            $tax_attribute[$key] = ['name' => $tax->name, 'rate' => $tax->rate];
                        }
                    }
                }
            }
            $currency_attribute = $this->addCurrencyAttributes($productid);

            return ['conditions' => $taxCondition, 'attributes' => ['tax' => $tax_attribute, 'currency' => $currency_attribute]];
        } catch (\Exception $ex) {
            dd($ex);

            throw new \Exception('Can not check the tax');
        }
    }

    public function checkTaxOld($isTaxApply, $id)
    {
        try {
            $rate1 = 0;
            $rate2 = 0;
            $name1 = 'null';
            $name2 = 'null';

            if ($ruleEnabled) {
                $enabled = $ruleEnabled->status;
                $type = $ruleEnabled->type;
                $compound = $ruleEnabled->compound;
                if ($enabled == 1 && $type == 'exclusive') {
                    if ($isTaxApply == 1) {
                        $tax1 = $this->tax->where('level', 1)->first();
                        $tax2 = $this->tax->where('level', 2)->first();
                        if ($tax1) {
                            $name1 = $tax1->name;
                            $rate1 = $tax1->rate;
                            $taxCondition1 = new \Darryldecode\Cart\CartCondition([
                                'name'   => $name1,
                                'type'   => 'tax',
                                'target' => 'item',
                                'value'  => $rate1.'%',
                            ]);
                        } else {
                            $taxCondition1 = new \Darryldecode\Cart\CartCondition([
                                'name'   => $name1,
                                'type'   => 'tax',
                                'target' => 'item',
                                'value'  => $rate1,
                            ]);
                        }
                        if ($tax2) {
                            $name2 = $tax2->name;
                            $rate2 = $tax2->rate;
                            $taxCondition2 = new \Darryldecode\Cart\CartCondition([
                                'name'   => $name2,
                                'type'   => 'tax',
                                'target' => 'item',
                                'value'  => $rate2.'%',
                            ]);
                        } else {
                            $taxCondition2 = new \Darryldecode\Cart\CartCondition([
                                'name'   => $name2,
                                'type'   => 'tax',
                                'target' => 'item',
                                'value'  => $rate2,
                            ]);
                        }
                    } else {
                        $taxCondition1 = new \Darryldecode\Cart\CartCondition([
                            'name'   => $name1,
                            'type'   => 'tax',
                            'target' => 'item',
                            'value'  => $rate1,
                        ]);
                        $taxCondition2 = new \Darryldecode\Cart\CartCondition([
                            'name'   => $name2,
                            'type'   => 'tax',
                            'target' => 'item',
                            'value'  => $rate2,
                        ]);
                    }
                    $currency_attribute = $this->addCurrencyAttributes($id);
                    if ($compound == 1) {
                        return ['conditions' => [$taxCondition1, $taxCondition2], 'attributes' => ['tax' => [['name' => $name1, 'rate' => $rate1], ['name' => $name2, 'rate' => $rate2]], 'currency' => $currency_attribute]];
                    } else {
                        return ['conditions' => $taxCondition2, 'attributes' => ['tax' => [['name' => $name2, 'rate' => $rate2]], 'currency' => $currency_attribute]];
                    }
                }
            }
        } catch (\Exception $ex) {
            dd($ex);

            throw new \Exception('Can not check the tax');
        }
    }

    public function cartRemove(Request $request)
    {
        $id = $request->input('id');
        Cart::remove($id);

        return 'success';
    }

    public function reduseQty(Request $request)
    {
        $id = $request->input('id');
        Cart::update($id, [
            'quantity' => -1, // so if the current product has a quantity of 4, it will subtract 1 and will result to 3
        ]);

        return 'success';
    }

    public function updateQty(Request $request)
    {
        $id = $request->input('productid');
        $qty = $request->input('qty');
        Cart::update($id, [
            'quantity' => [
                'relative' => false,
                'value'    => $qty,
            ],
        ]);

        return 'success';
    }

    public function addAddons($id)
    {
        $addon = $this->addons->where('id', $id)->first();
        $isTaxApply = $addon->tax_addon;
        $taxConditions = $this->CheckTax($isTaxApply);
        $items = ['id' => 'addon'.$addon->id, 'name' => $addon->name, 'price' => $addon->selling_price, 'quantity' => 1];
        $items = array_merge($items, $taxConditions);

        return $items;
    }

    public function getProductAddons($productId)
    {
        $addons = [];
        if ($this->addonRelation->where('product_id', $productId)->count() > 0) {
            $addid = $this->addonRelation->where('product_id', $productId)->pluck('addon_id')->toArray();
            $addons = $this->addons->whereIn('id', $addid)->get();
        }

        return $addons;
    }

    public function addProduct($id)
    {
        try {
            $qty = 1;

            $currency = $this->currency();
            $product = $this->product->where('id', $id)->first();
            if ($product) {
                $actualPrice = $this->cost($product->id);
                $currency = $this->currency();
                $productName = $product->name;
                $planid = 0;
                if ($this->checkPlanSession() == true) {
                    $planid = Session::get('plan');
                }
                $isTaxApply = $product->tax_apply;
                $taxConditions = $this->checkTax($id);

                /*
                 * Check if this product allow multiple qty
                 */
                if ($product->multiple_qty == 1) {
                    // Allow
                } else {
                    $qty = 1;
                }
                $items = ['id' => $id, 'name' => $productName, 'price' => $actualPrice, 'quantity' => $qty, 'attributes' => ['currency' => [[$currency]]]];
                $items = array_merge($items, $taxConditions);

                //dd($items);
                return $items;
            }
        } catch (\Exception $e) {
            dd($e);
        }
    }

    /**
     * @return type
     */
    public function clearCart()
    {
        foreach (Cart::getContent() as $item) {
            if (\Session::has('domain'.$item->id)) {
                \Session::forget('domain'.$item->id);
            }
        }
        $this->removePlanSession();
        $renew_control = new \App\Http\Controllers\Order\RenewController();
        $renew_control->removeSession();
        Cart::clear();

        return redirect('show/cart')->with('warning', 'Your cart is empty! ');
    }

    /**
     * @param type $id
     *
     * @throws \Exception
     *
     * @return type
     */
    public function licenceCart($id)
    {
        try {
            $licence = $this->licence->where('id', $id)->first();
            $isTaxApply = 0;
            $taxConditions = $this->CheckTax($isTaxApply);
            $items = ['id' => $licence->id, 'name' => $licence->name, 'price' => $licence->price, 'quantity' => 1, 'attributes' => ['number_of_sla' => $licence->number_of_sla]];
            $items = array_merge($items, $taxConditions);
            Cart::clear();
            Cart::add($items);

            return view('themes.default1.front.cart', compact('cartCollection'));
        } catch (\Exception $ex) {
            dd($ex);

            throw new \Exception('Problem while adding licence to cart');
        }
    }

    /**
     * @param type $id
     * @param type $key
     * @param type $value
     */
    public function cartUpdate($id, $key, $value)
    {
        try {
            Cart::update(
                $id,
                [
                $key => $value, // new item name
                    ]
            );
        } catch (\Exception $ex) {
        }
    }

    /**
     * @param type $id
     *
     * @return array
     */
    public function addCurrencyAttributes($id)
    {
        try {
            $currency = $this->currency();
            $product = $this->product->where('id', $id)->first();
            if ($product) {
                $productCurrency = $this->currency();
                $currency = $this->currency->where('code', $productCurrency)->get()->toArray();
            } else {
                $currency = [];
            }

            return $currency;
        } catch (\Exception $ex) {
        }
    }

    /**
     * @return type
     */
    public function addCouponUpdate()
    {
        try {
            $code = \Input::get('coupon');
            $cart = Cart::getContent();
            foreach ($cart as $item) {
                $id = $item->id;
            }
            $promo_controller = new \App\Http\Controllers\Payment\PromotionController();
            $result = $promo_controller->checkCode($code, $id);
            if ($result == 'success') {
                return redirect()->back()->with('success', \Lang::get('message.updated-successfully'));
            }

            return redirect()->back();
        } catch (\Exception $ex) {
            dd($ex);

            return redirect()->back()->with('fails', $ex->getMessage());
        }
    }

    /**
     * @param type $tax_class_id
     *
     * @throws \Exception
     *
     * @return type
     */
    public function getTaxByPriority($tax_class_id)
    {
        try {
            $taxe_relation = $this->tax->where('tax_classes_id', $tax_class_id)->orderBy('level')->get();

            return $taxe_relation;
        } catch (\Exception $ex) {
            dd($ex);

            throw new \Exception('error in get tax priority');
        }
    }

    /**
     * @param type $price
     *
     * @throws \Exception
     *
     * @return type
     */
    public static function rounding($price)
    {
        try {
            $tax_rule = new \App\Model\Payment\TaxOption();
            $rule = $tax_rule->findOrFail(1);
            $rounding = $rule->rounding;
            if ($rounding == 1) {
                return round($price);
            } else {
                return $price;
            }
        } catch (\Exception $ex) {
            throw new \Exception('error in get tax priority');
        }
    }

    /**
     * @return type
     */
    public function contactUs()
    {
        try {
            return view('themes.default1.front.contact');
        } catch (\Exception $ex) {
            return redirect()->back()->with('fails', $ex->getMessage());
        }
    }

    /**
     * @param Request $request
     *
     * @return type
     */
    public function postContactUs(Request $request)
    {
        $this->validate($request, [
            'name'    => 'required',
            'email'   => 'required|email',
            'message' => 'required',
        ]);

        $set = new \App\Model\Common\Setting();
        $set = $put->findOrFail(1);

        try {
            $from = $put->email;
            $fromname = $put->company;
            $toname = '';
            $to = 'support@ladybirdweb.com';
            $data = '';
            $data .= 'Name: '.$request->input('name').'<br/s>';
            $data .= 'Email: '.$request->input('email').'<br/>';
            $data .= 'Message: '.$request->input('message').'<br/>';
            $data .= 'Mobile: '.$request->input('Mobile').'<br/>';

            $subject = 'Faveo billing enquiry';
            $this->templateController->Mailing($from, $to, $data, $subject, [], $fromname, $toname);
            //$this->templateController->Mailing($from, $to, $data, $subject);
            return redirect()->back()->with('success', 'Your message was sent successfully. Thanks.');
        } catch (\Exception $ex) {
            return redirect()->back()->with('fails', $ex->getMessage());
        }
    }

    /**
     * @param type $slug
     *
     * @return type
     */
    public function addCartBySlug($slug)
    {
        try {
            $sub = '';
            if ($slug == 'helpdesk-with-kb-pro-edition') {
                $id = 8;
                $sub = 13;
            }
            if ($slug == 'helpdesk-and-kb-community') {
                $id = 7;
            }
            $url = url("pricing?id=$id&subscription=$sub");

            return \Redirect::to($url);
        } catch (\Exception $ex) {
            return redirect()->back()->with('fails', $ex->getMessage());
        }
    }

    /**
     * @param type $iso
     *
     * @throws \Exception
     *
     * @return string
     */
    public static function findCountryByGeoip($iso)
    {
        try {
            $country = \App\Model\Common\Country::where('country_code_char2', $iso)->first();
            if ($country) {
                return $country->country_code_char2;
            } else {
                return '';
            }
        } catch (\Exception $ex) {
            throw new \Exception($ex->getMessage());
        }
    }

    /**
     * @param type $code
     *
     * @throws \Exception
     *
     * @return type
     */
    public static function getCountryByCode($code)
    {
        try {
            $country = \App\Model\Common\Country::where('country_code_char2', $code)->first();
            if ($country) {
                return $country->country_name;
            }
        } catch (\Exception $ex) {
            throw new \Exception($ex->getMessage());
        }
    }

    /**
     * @param type $iso
     *
     * @throws \Exception
     *
     * @return array
     */
    public static function findStateByRegionId($iso)
    {
        try {
            if ($iso) {
                $states = \App\Model\Common\State::where('country_code_char2', $iso)->pluck('state_subdivision_name', 'state_subdivision_code')->toArray();
            } else {
                $states = [];
            }

            return $states;
        } catch (\Exception $ex) {
            throw new \Exception($ex->getMessage());
        }
    }

    /**
     * @param type $name
     *
     * @throws \Exception
     *
     * @return string
     */
    public static function getTimezoneByName($name)
    {
        try {
            if ($name) {
                $timezone = \App\Model\Common\Timezone::where('name', $name)->first();
                if ($timezone) {
                    $timezone = $timezone->id;
                } else {
                    $timezone = '114';
                }
            } else {
                $timezone = '114';
            }

            return $timezone;
        } catch (\Exception $ex) {
            throw new \Exception($ex->getMessage());
        }
    }

    /**
     * @param type $code
     *
     * @throws \Exception
     *
     * @return type
     */
    public static function getStateByCode($code)
    {
        try {
            $result = ['id' => '', 'name' => ''];
            if ($code) {
                $subregion = \App\Model\Common\State::where('state_subdivision_code', $code)->first();
                if ($subregion) {
                    $result = ['id' => $subregion->state_subdivision_code, 'name' => $subregion->state_subdivision_name];
                //return ['id' => $subregion->state_subdivision_code, 'name' => $subregion->state_subdivision_name];
                } else {
                    $result = '';
                }
            }

            return $result;
        } catch (\Exception $ex) {
            throw new \Exception($ex->getMessage());
        }
    }

    /**
     * @param type $id
     *
     * @throws \Exception
     *
     * @return type
     */
    public static function getStateNameById($id)
    {
        try {
            $name = '';
            $subregion = \App\Model\Common\State::where('state_subdivision_id', $id)->first();
            if ($subregion) {
                $name = $subregion->state_subdivision_name;
            }

            return $name;
        } catch (\Exception $ex) {
            throw new \Exception($ex->getMessage());
        }
    }

    /**
     * @param type $productid
     * @param type $price
     * @param type $cart
     * @param type $cart1
     * @param type $shop
     *
     * @return type
     */
    public static function calculateTax($productid, $price, $cart = 1, $cart1 = 0, $shop = 0)
    {
        try {
            $template_controller = new TemplateController();
            $result = $template_controller->checkTax($productid, $price, $cart, $cart1, $shop);
            // dd($result);
            $result = self::rounding($result);

            return $result;
        } catch (\Exception $ex) {
            return redirect()->back()->with('fails', $ex->getMessage());
        }
    }

    /**
     * @param type $rate
     * @param type $price
     *
     * @return type
     */
    public static function taxValue($rate, $price)
    {
        try {
            $tax = $price / (($rate / 100) + 1);
            $result = $price - $tax;
            $result = self::rounding($result);

            return $result;
        } catch (\Exception $ex) {
            return redirect()->back()->with('fails', $ex->getMessage());
        }
    }

    /**
     * @return type
     */
    public static function addons()
    {
        try {
            $items = Cart::getContent();
            $cart_productids = [];
            if (count($items) > 0) {
                foreach ($items as $key => $item) {
                    $cart_productids[] = $key;
                }
            }
            $_this = new self();
            $products = $_this->products($cart_productids);

            return $products;
        } catch (\Exception $ex) {
            return redirect()->back()->with('fails', $ex->getMessage());
        }
    }

    /**
     * @param type $ids
     *
     * @throws \Exception
     *
     * @return type
     */
    public function products($ids)
    {
        $parents_string = [];
        $parent = [];
        $productid = [];

        try {
            $parents = $this->product
                    ->whereNotNull('parent')
                    ->where('parent', '!=', 0)
                    ->where('category', 'addon')
                    ->pluck('parent', 'id')
                    ->toArray();
            foreach ($parents as $key => $parent) {
                if (is_array($parent)) {
                    $parent = implode(',', $parent);
                }
                $parents_string[$key] = $parent;
            }
            if (count($parents_string) > 0) {
                foreach ($parents_string as $key => $value) {
                    if (strpos($value, ',') !== false) {
                        $value = explode(',', $value);
                    }
                    $parent[$key] = $value;
                }
            }

            foreach ($parent as $key => $id) {
                if (in_array($id, $ids)) {
                    $productid[] = $key;
                }
                if (is_array($id)) {
                    foreach ($id as $i) {
                        if (in_array($i, $ids)) {
                            $productid[] = $key;
                        }
                    }
                }
            }
            $parent_products = $this->getProductById($productid);

            return $parent_products;
        } catch (\Exception $ex) {
            dd($ex);

            throw new \Exception($ex->getMessage());
        }
    }

    /**
     * @param type $ids
     *
     * @throws \Exception
     *
     * @return type
     */
    public function getProductById($ids)
    {
        try {
            $products = [];
            if (count($ids) > 0) {
                $products = $this->product
                        ->whereIn('id', $ids)
                        ->get();
            }

            return $products;
        } catch (\Exception $ex) {
            dd($ex);

            throw new \Exception($ex->getMessage());
        }
    }

    /**
     * @param type $iso
     *
     * @throws \Exception
     *
     * @return type
     */
    public static function getMobileCodeByIso($iso)
    {
        try {
            $code = '';
            if ($iso != '') {
                $mobile = \DB::table('mobile')->where('iso', $iso)->first();
                if ($mobile) {
                    $code = $mobile->phonecode;
                }
            }

            return $code;
        } catch (\Exception $ex) {
            throw new \Exception($ex->getMessage());
        }
    }

    /**
     * @param type $userid
     *
     * @throws \Exception
     *
     * @return string
     */
    public function currency($userid = '')
    {
        try {
            $currency = 'INR';
            if ($this->checkCurrencySession() == true) {
                $currency = Session::get('currency');
                // dd($currency);
            }

            if (\Auth::user()) {
                $currency = \Auth::user()->currency;
                if ($currency == 'USD' || $currency == '1') {
                    $currency = 'USD';
                }
            }
            if ($userid != '') {
                $user = new \App\User();
                $currency = $user->find($userid)->currency;
                if ($currency == 'USD' || $currency == '1') {
                    $currency = 'USD';
                } else {
                    $currency = 'INR';
                }
            }
            // dd($currency);
            return $currency;
        } catch (\Exception $ex) {
            throw new \Exception($ex->getMessage());
        }
    }

    /**
     * @param type $productid
     * @param type $userid
     * @param type $planid
     *
     * @return type
     */
    public function cost($productid, $userid = '', $planid = '')
    {
        try {
            $cost = $this->planCost($productid, $userid, $planid);
            if ($cost == 0) {
                $cost = $this->productCost($productid, $userid);
            }

            return self::rounding($cost);
        } catch (\Exception $ex) {
            // throw new \Exception($ex->getMessage());
        }
    }

    /**
     * @param type $productid
     * @param type $userid
     *
     * @throws \Exception
     *
     * @return type
     */
    public function productCost($productid, $userid = '')
    {
        try {
            $sales = 0;
            $currency = $this->currency($userid);
            $product = $this->product->find($productid);
            $price = $product->price()->where('currency', $currency)->first();
            if ($price) {
                $sales = $price->sales_price;
                if ($sales == 0) {
                    $sales = $price->price;
                }
            }
            //}

            return $sales;
        } catch (\Exception $ex) {
            throw new \Exception($ex->getMessage());
        }
    }

    /**
     * @param type $productid
     * @param type $userid
     * @param type $planid
     *
     * @throws \Exception
     *
     * @return type
     */
    public function planCost($productid, $userid, $planid = '')
    {
        try {
            $cost = 0;
            $subscription = $this->allowSubscription($productid);
            if ($this->checkPlanSession() == true) {
                $planid = Session::get('plan');
            }

            if ($subscription == true) {
                $plan = new \App\Model\Payment\Plan();
                $plan = $plan->where('id', $planid)->where('product', $productid)->first();

                if ($plan) {
                    $currency = $this->currency($userid);
                    $price = $plan->planPrice()
                                    ->where('currency', $currency)
                                    ->first()
                            ->add_price;
                    $days = $plan->days;
                    $months = $days / 30 / 12;
                    $cost = round($months) * $price;
                }
            }

            return $cost;
        } catch (\Exception $ex) {
            throw new \Exception($ex->getMessage());
        }
    }

    /**
     * @throws \Exception
     */
    public function removePlanSession()
    {
        try {
            if (Session::has('plan')) {
                Session::forget('plan');
            }
        } catch (\Exception $ex) {
            throw new \Exception($ex->getMessage());
        }
    }

    /**
     * @throws \Exception
     *
     * @return bool
     */
    public function checkPlanSession()
    {
        try {
            if (Session::has('plan')) {
                return true;
            }

            return false;
        } catch (\Exception $ex) {
            throw new \Exception($ex->getMessage());
        }
    }

    /**
     * @throws \Exception
     *
     * @return bool
     */
    public function checkCurrencySession()
    {
        try {
            if (Session::has('currency')) {
                return true;
            }

            return false;
        } catch (\Exception $ex) {
            throw new \Exception($ex->getMessage());
        }
    }

    /**
     * @param type $productid
     *
     * @throws \Exception
     *
     * @return bool
     */
    public function allowSubscription($productid)
    {
        try {
            $reponse = false;
            $product = $this->product->find($productid);
            if ($product) {
                if ($product->subscription == 1) {
                    $reponse = true;
                }
            }

            return $reponse;
        } catch (\Exception $ex) {
            throw new \Exception($ex->getMessage());
        }
    }
}
