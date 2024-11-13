<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Slide;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class AdminController extends Controller
{
    public function index() {
        $orders = Order::orderBy('created_at', 'DESC')->get()->take(10);
        $dashboardDatas = DB::select("SELECT SUM(total) As TotalAmount,
                SUM(if(status='ordered', total, 0)) As TotalOrderedAmount,
                SUM(if(status='delivered', total, 0)) As TotalDeliveredAmount,
                SUM(if(status='canceled', total, 0)) As TotalCanceledAmount,
                Count(*) as Total,
                SUM(if(status='ordered', 1, 0)) As TotalOrdered,
                SUM(if(status='delivered', 1, 0)) As TotalDelivered,
                SUM(if(status='canceled', 1, 0)) As TotalCanceled
                FROM orders
                ");
        DB::select("set @@sql_mode = ''");

        $monthDatas = DB::select("SELECT M.id AS MonthNo, M.name AS MONTHNAME,
                        IFNULL(D.TotalAmount, 0) AS TotalAmount,
                        IFNULL(D.TotalOrderedAmount, 0) AS TotalOrderedAmount,
                        IFNULL(D.TotalDeliveredAmount, 0) AS TotalDeliveredAmount,
                        IFNULL(D.TotalCanceledAmount, 0) AS TotalCanceledAmount
                        FROM month_names M LEFT JOIN (
                        SELECT MONTH(created_at) AS MonthNo,
                        SUM(total) AS TotalAmount,
                        SUM(if(status='ordered', total, 0)) AS TotalOrderedAmount,
                        SUM(if(status='delivered', total, 0)) AS TotalDeliveredAmount,
                        SUM(if(status='canceled', total, 0)) AS TotalCanceledAmount
                        FROM orders WHERE YEAR(created_at)=YEAR(NOW()) GROUP BY YEAR(created_at) -- , MONTH(created_at), DATE_FORMAT(created_at, '%d')
                        ORDER BY MONTH(created_at)) D ON D.MonthNo=M.id");

        $AmountM = implode(',', collect($monthDatas)->pluck('TotalAmount')->ToArray());
        $OrderedAmountM = implode(',', collect($monthDatas)->pluck('TotalOrderedAmount')->ToArray());
        $DeliveredAmountM = implode(',', collect($monthDatas)->pluck('TotalDeliveredAmount')->ToArray());
        $CanceledAmountM = implode(',', collect($monthDatas)->pluck('TotalCanceledAmount')->ToArray());

        $TotalAmount = collect($monthDatas)->sum('TotalAmount');
        $TotalOrderedAmount = collect($monthDatas)->sum('TotalOrderedAmount');
        $TotalDeliveredAmount = collect($monthDatas)->sum('TotalDeliveredAmount');
        $TotalCanceledAmount = collect($monthDatas)->sum('TotalCanceledAmount');

        return view('admin.index', compact('orders', 'dashboardDatas', 'AmountM', 'OrderedAmountM', 'DeliveredAmountM', 'CanceledAmountM', 'TotalAmount', 'TotalOrderedAmount', 'TotalDeliveredAmount', 'TotalCanceledAmount'));
    }

    public function brands() {
        $brands = Brand::orderBy('id', 'desc')->paginate(10);

        return view('admin.brands', compact('brands'));
    }

    // Brand Add
    public function add_brand() {
        return view('admin.brand-add');
    }

    public function brand_store(Request $request) {
        $request->validate([
            'name' => 'required|max:255',
            'slug' => 'required|unique:brands,slug',
            'image' => 'mimes:jpg,jpeg,png,webp|max:2048'
        ]);

        $brand = new Brand();
        $brand->name = $request->input('name');
        $brand->slug = Str::slug($request->name);
        if($request->hasFile('image')) {
            $image = $request->file('image');
            $file_extension = $image->getClientOriginalExtension();
            $filename = Carbon::now()->timestamp.'.'.$file_extension;
            // 썸네일 저장
            $this->GenerateBrandThumbnail($image, $filename);
            // 원본 이미지 업로드
            // $image->move(public_path('/uploads/brands/'), $filename);
            $brand->image = $filename;
        }

        $brand->save();
        return redirect()->route('admin.brands')->with('success', 'Brand added successfully');
    }

    public function brand_edit($id) {
        $brand = Brand::find($id);
        return view('admin.brand-edit', compact('brand'));
    }

    public function brand_update(Request $request) {
        $request->validate([
            'name' => 'required|max:255',
            'slug' => 'required|unique:brands,slug',
            'image' => 'mimes:jpg,jpeg,png,webp|max:2048'
        ]);


        $brand = Brand::find($request->id);
        $brand->name = $request->input('name');
        $brand->slug = Str::slug($request->name);
        if($request->hasFile('image')) {
            // 기존 이미지 삭제
            if(File::exists(public_path('/uploads/brands/').$brand->image)) {
                File::delete(public_path('/uploads/brands/'.$brand->image));
            }
            $image = $request->file('image');
            $file_extension = $image->getClientOriginalExtension();
            $filename = Carbon::now()->timestamp.'.'.$file_extension;
            // 썸네일 저장
            $this->GenerateBrandThumbnail($image, $filename);
            // 원본 이미지 업로드
            // $image->move(public_path('/uploads/brands/'), $filename);
            $brand->image = $filename;
        } else {
            dd($request->all());
        }

        $brand->save();
        return redirect()->route('admin.brands')->with('success', 'Brand updated successfully');
    }

    public function brand_delete($id) {
        $brand = Brand::find($id);

        // 이미지 파일 삭제
        if(File::exists(public_path('/uploads/brands/').$brand->image)) {
            File::delete(public_path('/uploads/brands/' . $brand->image));
        }
        $brand->delete();

        return redirect()->route('admin.brands')->with('success', 'Brand has been deleted successfully');
    }

    public function categories() {
        $categories = Category::orderBy('id', 'DESC')->paginate(10);

        return view('admin.categories', compact('categories'));
    }

    public function category_add() {
        return view('admin.category-add');
    }

    public function category_store(Request $request) {
        $request->validate([
            'name' => 'required|max:255',
            'slug' => 'required|unique:categories,slug',
            'image' => 'mimes:jpg,jpeg,png,webp|max:2048'
        ]);

        $category = new Category();
        $category->name = $request->name;
        $category->slug = Str::slug($request->slug);
        if($request->hasFile('image')) {
            $image = $request->file('image');
            $file_extension = $image->getClientOriginalExtension();
            $filename = Carbon::now()->timestamp.'.'.$file_extension;
            // 썸네일 저장
            $this->GenerateCategoryThumbnail($image, $filename);
            // 원본 이미지 업로드
            // $image->move(public_path('/uploads/categories/'), $filename);
            $category->image = $filename;
        }
        $category->save();
        return redirect()->route('admin.categories')->with('success', 'Category added successfully');
    }

    public function category_edit($id) {
        $category = Category::findOrFail($id);
        return view('admin.category-edit', compact('category'));
    }

    public function category_update(Request $request) {
        $request->validate([
            'name' => 'required|max:255',
            'slug' => 'required|unique:categories,slug',
            'image' => 'mimes:jpg,jpeg,png,webp|max:2048'
        ]);

        $category = Category::findOrFail($request->id);
        $category->name = $request->name;
        $category->slug = Str::slug($request->slug);
        if($request->hasFile('image')) {
            // 기존 이미지 삭제
            if(File::exists(public_path('/uploads/categories/').$category->image)) {
                File::delete(public_path('/uploads/categories/'.$category->image));
            }
            $image = $request->file('image');
            $file_extension = $image->getClientOriginalExtension();
            $filename = Carbon::now()->timestamp.'.'.$file_extension;
            // 썸네일 저장
            $this->GenerateCategoryThumbnail($image, $filename);
            // 원본 이미지 업로드
            // $image->move(public_path('/uploads/categories/'), $filename);
            $category->image = $filename;
        }
        $category->save();
        return redirect()->route('admin.categories')->with('success', 'Category updated successfully');
    }

    public function category_delete($id) {
        $category = Category::findOrFail($id);
        if(File::exists(public_path('/uploads/categories/'.$category->image))) {
            File::delete(public_path('/uploads/categories/'.$category->image));
        }
        $category->delete();
        return redirect()->route('admin.categories')->with('success', 'Category has been deleted successfully');
    }

    public function GenerateBrandThumbnail($image, $imageName) {
        $destinationPath = public_path('/uploads/brands');
        $img = Image::read($image->path());
        $img->cover(124, 124, "top");
        $img->resize(124, 124, function($constraint) {
            $constraint->aspectRatio();
        })->save($destinationPath . '/' . $imageName);
    }

    public function GenerateCategoryThumbnail($image, $imageName) {
        $destinationPath = public_path('/uploads/categories');
        $img = Image::read($image->path());
        $img->cover(124, 124, "top");
        $img->resize(124, 124, function($constraint) {
            $constraint->aspectRatio();
        })->save($destinationPath . '/' . $imageName);
    }

    public function products() {
        $products = Product::orderBy('id', 'desc')->paginate(10);

        return view('admin.products', compact('products'));
    }

    public function product_add() {
        $categories = Category::orderBy('id', 'desc')->get();
        $brands = Brand::orderBy('id', 'desc')->get();
        return view('admin.product-add', compact('brands', 'categories'));
    }

    public function product_store(Request $request) {
        $request->validate([
            'name' => 'required|max:255',
            'slug' => 'required|unique:products,slug',
            'short_description' => 'required',
            'description' => 'required',
            'regular_price' => 'required',
            'sale_price' => 'required',
            'SKU' => 'required',
            'stock_status' => 'required',
            'featured' => 'required',
            'quantity' => 'required',
            'image' => 'required|mimes:jpg,jpeg,png,webp|max:2048',
            'brand_id' => 'required',
            'category_id' => 'required',
        ]);

        $product = new Product();
        $product->name = $request->name;
        $product->slug = Str::slug($request->name);
        $product->short_description = $request->short_description;
        $product->description = $request->description;
        $product->regular_price = $request->regular_price;
        $product->sale_price = $request->sale_price;
        $product->SKU = $request->SKU;
        $product->stock_status = $request->stock_status;
        $product->featured = $request->featured;
        $product->quantity = $request->quantity;
        $product->brand_id = $request->brand_id;
        $product->category_id = $request->category_id;

        // 메인 이미지 업로드
        if($request->hasFile('image')) {
            $image = $request->file('image');
            $file_extension = $image->getClientOriginalExtension();
            $filename = Carbon::now()->timestamp.'.'.$file_extension;
            $this->GenerateProductThumbnail($image, $filename);
            $image->move(public_path('/uploads/products/'), $filename);
            $product->image = $filename;
        }

        // 상세 이미지 업로드
        if($request->hasFile('images')) {
            $gallery_arr = [];
            $gallery_img = "";
            foreach($request->file('images') as $image) {
                $file_ext = $image->getClientOriginalExtension();
                $file_name = Carbon::now()->timestamp . '_' . uniqid() . '.'.$file_ext;
                $this->GenerateProductThumbnail($image, $file_name);
                $image->move(public_path('/uploads/products/'), $file_name);
                $gallery_arr[] = $file_name;
            }

            $product->images = Arr::join($gallery_arr, ',');
        }

        $product->save();
        return redirect()->route('admin.products')->with('success', 'Product added successfully');
    }

    public function product_edit($id) {
        $product = Product::findOrFail($id);
        $categories = Category::orderBy('id', 'desc')->get();
        $brands = Brand::orderBy('id', 'desc')->get();

        return view('admin.product-edit', compact('product', 'categories', 'brands'));
    }

    public function product_update(Request $request) {
        $request->validate([
            'name' => 'required|max:255',
            'slug' => 'required|unique:products,slug,' . $request->id,
            'short_description' => 'required',
            'description' => 'required',
            'regular_price' => 'required',
            'sale_price' => 'required',
            'SKU' => 'required',
            'stock_status' => 'required',
            'featured' => 'required',
            'quantity' => 'required',
            'image' => 'required|mimes:jpg,jpeg,png,webp|max:2048',
            'brand_id' => 'required',
            'category_id' => 'required',
        ]);

        $product = Product::findOrFail($request->id);
        $product->name = $request->name;
        $product->slug = Str::slug($request->name);
        $product->short_description = $request->short_description;
        $product->description = $request->description;
        $product->regular_price = $request->regular_price;
        $product->sale_price = $request->sale_price;
        $product->SKU = $request->SKU;
        $product->stock_status = $request->stock_status;
        $product->featured = $request->featured;
        $product->quantity = $request->quantity;
        $product->brand_id = $request->brand_id;
        $product->category_id = $request->category_id;

        // 메인 이미지 업로드
        if($request->hasFile('image')) {
            // 기존 이미지 삭제
            if(File::exists(public_path('/uploads/products/').$product->image)) {
                File::delete(public_path('/uploads/products/').$product->image);
                File::delete(public_path('/uploads/products/resize_').$product->image);
                File::delete(public_path('/uploads/products/thumbnails/thumb_').$product->image);
            }
            $image = $request->file('image');

            $file_extension = $image->getClientOriginalExtension();
            $filename = Carbon::now()->timestamp.'.'.$file_extension;
            $this->GenerateProductThumbnail($image, $filename);
            $image->move(public_path('/uploads/products/'), $filename);
            $product->image = $filename;
        }

        // 상세 이미지 업로드
        if($request->hasFile('images')) {
            // 기존 이미지 삭제
            $oldImgs = explode(',', $product->images);
            foreach ($oldImgs as $img) {
                if (File::exists(public_path('/uploads/products/' . $img))) {
                    File::delete(public_path('/uploads/products/' . $img));
                    File::delete(public_path('/uploads/products/resize_' . $img));
                    File::delete(public_path('/uploads/products/thumbnails/' . $img));
                    File::delete(public_path('/uploads/products/thumbnails/thumb_' . $img));
                }
            }
            $gallery_arr = [];
            $gallery_img = "";
            foreach ($request->file('images') as $image) {
                $file_ext = $image->getClientOriginalExtension();
                $file_name = Carbon::now()->timestamp . '_' . uniqid() . '.' . $file_ext;
                echo "2: Image: " . $image->getClientOriginalName() . " // fileName: " . $file_name . "<br>\n";
                $this->GenerateProductThumbnail($image, $file_name);
                $image->move(public_path('/uploads/products/'), $file_name);
                //$img[] = $file_name;
                $gallery_arr[] = $file_name;
            }

            $product->images = Arr::join($gallery_arr, ',');
        }

        $product->save();
        return redirect()->route('admin.products')->with('success', 'Product updated successfully');
    }

    public function product_delete($id) {
        $product = Product::findOrFail($id);
        // 이미지 삭제 1
        if(File::exists(public_path('/uploads/products/').$product->image)) {
            File::delete(public_path('/uploads/products/').$product->image);
            File::delete(public_path('/uploads/products/resize_').$product->image);
            File::delete(public_path('/uploads/products/thumbnails/thumb_').$product->image);
        }

        // 상세 이미지 삭제
        $oldImgs = explode(',', $product->images);
        foreach ($oldImgs as $img) {
            if (File::exists(public_path('/uploads/products/' . $img))) {
                File::delete(public_path('/uploads/products/' . $img));
                File::delete(public_path('/uploads/products/resize_' . $img));
                File::delete(public_path('/uploads/products/thumbnails/thumb_' . $img));
            }
        }
        $product->delete();

        return redirect()->route('admin.products')->with('success', 'Product deleted successfully');
    }

    public function GenerateProductThumbnail($image, $imageName) {
        echo "<pre>\n"; print_r($image); echo "\n</pre>\n<br>\n";
        echo "Image: " . $image->path() . " // ImageName : " . $imageName . "<br>\n";
        $destinationPathThumbnail = public_path('/uploads/products/thumbnails');
        $destinationPath = public_path('/uploads/products');
    	$img = Image::read($image->path());

        $img->cover(540, 689, "top");
    	$img->resize(540, 689, function ($constraint) {
    		$constraint->aspectRatio();
    	})->save($destinationPath . '/resize_' . $imageName);

        $img->resize(104, 104, function ($constraint) {
    		$constraint->aspectRatio();
    	})->save($destinationPathThumbnail . '/thumb_' . $imageName);
    }

    public function coupons() {
        $coupons = Coupon::orderBy('expiry_date', 'desc')->paginate(12);
        return view('admin.coupons', compact('coupons'));
    }

    public function coupon_add() {
        return view('admin.coupon-add');
    }

    public function coupon_store(Request $request) {
        $request->validate([
            'code' => 'required',
            'type' => 'required',
            'value' => 'required|numeric',
            'cart_value' => 'required|numeric',
            'expiry_date' => 'required|date',
        ]);

        $coupon = new Coupon();
        $coupon->code = $request->code;
        $coupon->type = $request->type;
        $coupon->value = $request->value;
        $coupon->cart_value = $request->cart_value;
        $coupon->expiry_date = $request->expiry_date;
        $coupon->save();

        return redirect()->route('admin.coupons')->with('success', 'Coupon has been added successfully.');
    }

    public function coupon_edit($id) {
        $coupon = Coupon::findOrFail($id);
        return view('admin.coupon-edit', compact('coupon'));
    }

    public function coupon_update(Request $request) {
        $request->validate([
            'code' => 'required',
            'type' => 'required',
            'value' => 'required|numeric',
            'cart_value' => 'required|numeric',
            'expiry_date' => 'required|date',
        ]);

        $coupon = Coupon::findOrFail($request->id);
        $coupon->code = $request->code;
        $coupon->type = $request->type;
        $coupon->value = $request->value;
        $coupon->cart_value = $request->cart_value;
        $coupon->expiry_date = $request->expiry_date;
        $coupon->save();

        return redirect()->route('admin.coupons')->with('success', 'Coupon has been updated successfully.');
    }

    public function coupon_delete($id) {
        $coupon = Coupon::find($id);
        $coupon->delete();

        return redirect()->route('admin.coupons')->with('success', 'Coupon has been deleted successfully.');
    }

    public function orders() {
        $orders = Order::orderBy('created_at', 'desc')->paginate(12);
        return view('admin.orders', compact('orders'));
    }

    public function order_details($order_id) {
        $order = Order::findOrFail($order_id);
        $orderItems = OrderItem::where('order_id', $order_id)->orderBy('id')->paginate(12);
        $transaction = Transaction::where('order_id', $order_id)->first();
        return view('admin.order-details', compact('order', 'orderItems', 'transaction'));
    }

    public function update_order_status(Request $request) {
        $order = Order::find($request->order_id);
        $order->status = $request->order_status;
        if($request->order_status == 'delivered') {
            $order->delivered_date = Carbon::now();
        } elseif($request->order_status == 'canceled') {
            $order->canceled_date = Carbon::now();
        }
        $order->save();

        if($request->order_status == 'delivered') {
            $transaction = Transaction::where('order_id', $request->order_id)->first();
            $transaction->status = 'approved';
            $transaction->save();
        }

        return back()->with('success', 'Status changed successfully.');
    }

    public function slides() {
        $slides = Slide::orderBy('id', 'desc')->paginate(10);
        return view('admin.slides', compact('slides'));
    }

    public function slide_add() {
        return view('admin.slide-add');
    }

    public function slide_store(Request $request) {
        $request->validate([
            'tagline' => 'required',
            'title' => 'required',
            'subtitle' => 'required',
            'link' => 'required|url:http,https',
            'status' => 'required',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $slide = new Slide();
        $slide->tagline = $request->tagline;
        $slide->title = $request->title;
        $slide->subtitle = $request->subtitle;
        $slide->link = $request->link;
        $slide->status = $request->status;

        $image = $request->file('image');
        $file_extension = $image->getClientOriginalExtension();
        $file_name = Carbon::now()->timestamp . '_' . uniqid() . '.' . $file_extension;
        $this->GenerateSlideThumbnail($image, $file_name);
        $image->move(public_path('/uploads/slides'), $file_name);
        $slide->image = $file_name;
        $slide->save();

        return redirect()->route('admin.slides')->with('success', 'Slide added successfully.');
    }

    public function GenerateSlideThumbnail($image, $imageName) {
        $destinationPath = public_path('/uploads/slides');
        $img = Image::read($image->path());
        $img->cover(400, 690, "top");
        $img->resize(400, 690, function($constraint) {
            $constraint->aspectRatio();
        })->save($destinationPath . '/resize_' . $imageName);
    }

    public function slide_edit($slide_id) {
        $slide = Slide::find($slide_id);
        return view('admin.slide-edit', compact('slide'));
    }

    public function slide_update(Request $request) {
        $request->validate([
    			'tagline' => 'required',
    			'title' => 'required',
    			'subtitle' => 'required',
    			'link' => 'required|url:http,https',
    			'status' => 'required',
    		]);
    		$slide = Slide::findOrFail($request->id);
    		$slide->tagline = $request->tagline;
    		$slide->title = $request->title;
    		$slide->subtitle = $request->subtitle;
    		$slide->link = $request->link;
    		$slide->status = $request->status;
    		if ($request->hasFile('image')) {
                if(File::exists(public_path('/uploads/slides/' . $slide->image))) {
                    File::delete(public_path('/uploads/slides/' . $slide->image));
                    // Resize
                    if(File::exists(public_path('/uploads/slides/resize_' . $slide->image))) {
                        File::delete(public_path('/uploads/slides/resize_' . $slide->image));
                    }
                }
    			$image = $request->file('image');
    			$file_extension = $image->getClientOriginalExtension();
    			$file_name = Carbon::now()->timestamp . '_' . uniqid() . '.' . $file_extension;
    			$this->GenerateSlideThumbnail($image, $file_name);
    			$image->move(public_path('/uploads/slides'), $file_name);
    			$slide->image = $file_name;
    		}
    		$slide->save();
    		return redirect()->route('admin.slides')->with('success', 'Slide updated successfully.');
    }

    public function slide_delete($slide_id) {
        $slide = Slide::find($slide_id);
        if(File::exists(public_path('/uploads/slides/' . $slide->image))) {
            File::delete(public_path('/uploads/slides/' . $slide->image));
            // Resize
            if(File::exists(public_path('/uploads/slides/resize_' . $slide->image))) {
                File::delete(public_path('/uploads/slides/resize_' . $slide->image));
            }
        }
        $slide->delete();

        return redirect()->route('admin.slides')->with('success', 'Slide deleted successfully.');
    }
}
