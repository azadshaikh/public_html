Astero.Sections.add('products/products-1', {
    name: 'Products 1',
    image: Astero.builderAssetsUrl + '/screenshots/sections/products/products-1-thumb.jpeg',
    html: `<section class="products-1" title="latest-products-1">
  <div class="container">
    <div class="row">
      <div class="col-12">
        <div class="section-heading text-center">
          <h2>Popular Products</h2>
        </div>
      </div>
    </div>
  </div>


  <div class="container" data-v-component-products="popular" data-v-limit="8" data-v-image_size="medium">
    <div class="row">



      <div class="col-md-3" data-v-product>

        <article class="single-product-wrapper">
          <!-- Product Image -->
          <a href="#" data-v-product-url> </a>
          <div class="product-image">
            <a href="#" data-v-product-url>

              <img src="${Astero.builderAssetsUrl}/images/demo/product.jpg" loading="lazy" data-v-product-alt alt="" data-v-size="thumb"  data-v-product-image />

              <!-- Hover Thumb -->
              <img class="hover-img" src="${Astero.builderAssetsUrl}/images/demo/product-2.jpg" loading="lazy" data-v-product-alt alt=""  data-v-size="thumb" data-v-product-image-1 />
            </a>

            <!-- Favourite -->
            <div class="product-favourite">
              <a href="#" class="la la-heart" data-v-astero-action="addToWishlist" data-v-product-add_wishlist_url>
                <span></span>
              </a>
            </div>

            <!-- Compare -->
            <div class="product-compare">
              <a href="#" class="la la-random" data-v-astero-action="addToCompare" data-v-product-add_compare_url>
                <span></span>
              </a>
            </div>
          </div>

          <!-- Product Description -->
          <div class="product-content">

            <a href="#" class="text-body" data-v-product-url>
              <span data-v-product-name>Product 8</span>
            </a>

            <p class="product-price" data-v-if="_product.price > 0" data-v-product-price_tax_formatted>100.0000</p>

            <!-- Hover Content -->
            <div class="hover-content" data-v-if="_product.price > 0">
              <!-- Add to Cart -->
              <div class="add-to-cart-btn">
                <input type="hidden" name="product_id" value="" data-v-product-product_id />
                <a href="" class="btn btn-primary w-100" data-v-product-add_cart_url data-v-astero-action="addToCart" data-product_id="1">
                  <span class="loading d-none">
                    <span class="spinner-border spinner-border-sm align-middle" role="status" aria-hidden="true"> </span>
                    <span>Add to cart</span>...
                  </span>

                  <span class="button-text">
                    Add to cart
                  </span>
                </a>
              </div>
            </div>
          </div>
        </article>


      </div>



      <div class="col-md-3" data-v-product>

        <article class="single-product-wrapper">
          <!-- Product Image -->
          <a href="#" data-v-product-url> </a>
          <div class="product-image">
            <a href="#" data-v-product-url>

              <img src="${Astero.builderAssetsUrl}/images/demo/product.jpg" loading="lazy" data-v-product-alt alt="" data-v-size="thumb"  data-v-product-image />

              <!-- Hover Thumb -->
              <img class="hover-img" src="${Astero.builderAssetsUrl}/images/demo/product-2.jpg" loading="lazy" data-v-product-alt alt=""  data-v-size="thumb" data-v-product-image-1 />
            </a>

            <!-- Favourite -->
            <div class="product-favourite">
              <a href="#" class="la la-heart" data-v-astero-action="addToWishlist" data-v-product-add_wishlist_url>
                <span></span>
              </a>
            </div>

            <!-- Compare -->
            <div class="product-compare">
              <a href="#" class="la la-random" data-v-astero-action="addToCompare" data-v-product-add_compare_url>
                <span></span>
              </a>
            </div>
          </div>

          <!-- Product Description -->
          <div class="product-content">

            <a href="#" class="text-body" data-v-product-url>
              <span data-v-product-name>Product 8</span>
            </a>

            <p class="product-price" data-v-if="_product.price > 0" data-v-product-price_tax_formatted>100.0000</p>

            <!-- Hover Content -->
            <div class="hover-content" data-v-if="_product.price > 0">
              <!-- Add to Cart -->
              <div class="add-to-cart-btn">
                <input type="hidden" name="product_id" value="" data-v-product-product_id />
                <a href="" class="btn btn-primary w-100" data-v-product-add_cart_url data-v-astero-action="addToCart" data-product_id="1">
                  <span class="loading d-none">
                    <span class="spinner-border spinner-border-sm align-middle" role="status" aria-hidden="true"> </span>
                    <span>Add to cart</span>...
                  </span>

                  <span class="button-text">
                    Add to cart
                  </span>
                </a>
              </div>
            </div>
          </div>
        </article>


      </div>



      <div class="col-md-3" data-v-product>

        <article class="single-product-wrapper">
          <!-- Product Image -->
          <a href="#" data-v-product-url> </a>
          <div class="product-image">
            <a href="#" data-v-product-url>

              <img src="${Astero.builderAssetsUrl}/images/demo/product.jpg" loading="lazy" data-v-product-alt alt="" data-v-size="thumb"  data-v-product-image />

              <!-- Hover Thumb -->
              <img class="hover-img" src="${Astero.builderAssetsUrl}/images/demo/product-2.jpg" loading="lazy" data-v-product-alt alt=""  data-v-size="thumb" data-v-product-image-1 />
            </a>

            <!-- Favourite -->
            <div class="product-favourite">
              <a href="#" class="la la-heart" data-v-astero-action="addToWishlist" data-v-product-add_wishlist_url>
                <span></span>
              </a>
            </div>

            <!-- Compare -->
            <div class="product-compare">
              <a href="#" class="la la-random" data-v-astero-action="addToCompare" data-v-product-add_compare_url>
                <span></span>
              </a>
            </div>
          </div>

          <!-- Product Description -->
          <div class="product-content">

            <a href="#" class="text-body" data-v-product-url>
              <span data-v-product-name>Product 8</span>
            </a>

            <p class="product-price" data-v-if="_product.price > 0" data-v-product-price_tax_formatted>100.0000</p>

            <!-- Hover Content -->
            <div class="hover-content" data-v-if="_product.price > 0">
              <!-- Add to Cart -->
              <div class="add-to-cart-btn">
                <input type="hidden" name="product_id" value="" data-v-product-product_id />
                <a href="" class="btn btn-primary w-100" data-v-product-add_cart_url data-v-astero-action="addToCart" data-product_id="1">
                  <span class="loading d-none">
                    <span class="spinner-border spinner-border-sm align-middle" role="status" aria-hidden="true"> </span>
                    <span>Add to cart</span>...
                  </span>

                  <span class="button-text">
                    Add to cart
                  </span>
                </a>
              </div>
            </div>
          </div>
        </article>


      </div>



      <div class="col-md-3" data-v-product>

        <article class="single-product-wrapper">
          <!-- Product Image -->
          <a href="#" data-v-product-url> </a>
          <div class="product-image">
            <a href="#" data-v-product-url>

              <img src="${Astero.builderAssetsUrl}/images/demo/product.jpg" loading="lazy" data-v-product-alt alt="" data-v-size="thumb"  data-v-product-image />

              <!-- Hover Thumb -->
              <img class="hover-img" src="${Astero.builderAssetsUrl}/images/demo/product-2.jpg" loading="lazy" data-v-product-alt alt=""  data-v-size="thumb" data-v-product-image-1 />
            </a>

            <!-- Favourite -->
            <div class="product-favourite">
              <a href="#" class="la la-heart" data-v-astero-action="addToWishlist" data-v-product-add_wishlist_url>
                <span></span>
              </a>
            </div>

            <!-- Compare -->
            <div class="product-compare">
              <a href="#" class="la la-random" data-v-astero-action="addToCompare" data-v-product-add_compare_url>
                <span></span>
              </a>
            </div>
          </div>

          <!-- Product Description -->
          <div class="product-content">

            <a href="#" class="text-body" data-v-product-url>
              <span data-v-product-name>Product 8</span>
            </a>

            <p class="product-price" data-v-if="_product.price > 0" data-v-product-price_tax_formatted>100.0000</p>

            <!-- Hover Content -->
            <div class="hover-content" data-v-if="_product.price > 0">
              <!-- Add to Cart -->
              <div class="add-to-cart-btn">
                <input type="hidden" name="product_id" value="" data-v-product-product_id />
                <a href="" class="btn btn-primary w-100" data-v-product-add_cart_url data-v-astero-action="addToCart" data-product_id="1">
                  <span class="loading d-none">
                    <span class="spinner-border spinner-border-sm align-middle" role="status" aria-hidden="true"> </span>
                    <span>Add to cart</span>...
                  </span>

                  <span class="button-text">
                    Add to cart
                  </span>
                </a>
              </div>
            </div>
          </div>
        </article>


      </div>



      <div class="col-md-3" data-v-product>

        <article class="single-product-wrapper">
          <!-- Product Image -->
          <a href="#" data-v-product-url> </a>
          <div class="product-image">
            <a href="#" data-v-product-url>

              <img src="${Astero.builderAssetsUrl}/images/demo/product.jpg" loading="lazy" data-v-product-alt alt="" data-v-size="thumb"  data-v-product-image />

              <!-- Hover Thumb -->
              <img class="hover-img" src="${Astero.builderAssetsUrl}/images/demo/product-2.jpg" loading="lazy" data-v-product-alt alt=""  data-v-size="thumb" data-v-product-image-1 />
            </a>

            <!-- Favourite -->
            <div class="product-favourite">
              <a href="#" class="la la-heart" data-v-astero-action="addToWishlist" data-v-product-add_wishlist_url>
                <span></span>
              </a>
            </div>

            <!-- Compare -->
            <div class="product-compare">
              <a href="#" class="la la-random" data-v-astero-action="addToCompare" data-v-product-add_compare_url>
                <span></span>
              </a>
            </div>
          </div>

          <!-- Product Description -->
          <div class="product-content">

            <a href="#" class="text-body" data-v-product-url>
              <span data-v-product-name>Product 8</span>
            </a>

            <p class="product-price" data-v-if="_product.price > 0" data-v-product-price_tax_formatted>100.0000</p>

            <!-- Hover Content -->
            <div class="hover-content" data-v-if="_product.price > 0">
              <!-- Add to Cart -->
              <div class="add-to-cart-btn">
                <input type="hidden" name="product_id" value="" data-v-product-product_id />
                <a href="" class="btn btn-primary w-100" data-v-product-add_cart_url data-v-astero-action="addToCart" data-product_id="1">
                  <span class="loading d-none">
                    <span class="spinner-border spinner-border-sm align-middle" role="status" aria-hidden="true"> </span>
                    <span>Add to cart</span>...
                  </span>

                  <span class="button-text">
                    Add to cart
                  </span>
                </a>
              </div>
            </div>
          </div>
        </article>


      </div>



      <div class="col-md-3" data-v-product>

        <article class="single-product-wrapper">
          <!-- Product Image -->
          <a href="#" data-v-product-url> </a>
          <div class="product-image">
            <a href="#" data-v-product-url>

              <img src="${Astero.builderAssetsUrl}/images/demo/product.jpg" loading="lazy" data-v-product-alt alt="" data-v-size="thumb"  data-v-product-image />

              <!-- Hover Thumb -->
              <img class="hover-img" src="${Astero.builderAssetsUrl}/images/demo/product-2.jpg" loading="lazy" data-v-product-alt alt=""  data-v-size="thumb" data-v-product-image-1 />
            </a>

            <!-- Favourite -->
            <div class="product-favourite">
              <a href="#" class="la la-heart" data-v-astero-action="addToWishlist" data-v-product-add_wishlist_url>
                <span></span>
              </a>
            </div>

            <!-- Compare -->
            <div class="product-compare">
              <a href="#" class="la la-random" data-v-astero-action="addToCompare" data-v-product-add_compare_url>
                <span></span>
              </a>
            </div>
          </div>

          <!-- Product Description -->
          <div class="product-content">

            <a href="#" class="text-body" data-v-product-url>
              <span data-v-product-name>Product 8</span>
            </a>

            <p class="product-price" data-v-if="_product.price > 0" data-v-product-price_tax_formatted>100.0000</p>

            <!-- Hover Content -->
            <div class="hover-content" data-v-if="_product.price > 0">
              <!-- Add to Cart -->
              <div class="add-to-cart-btn">
                <input type="hidden" name="product_id" value="" data-v-product-product_id />
                <a href="" class="btn btn-primary w-100" data-v-product-add_cart_url data-v-astero-action="addToCart" data-product_id="1">
                  <span class="loading d-none">
                    <span class="spinner-border spinner-border-sm align-middle" role="status" aria-hidden="true"> </span>
                    <span>Add to cart</span>...
                  </span>

                  <span class="button-text">
                    Add to cart
                  </span>
                </a>
              </div>
            </div>
          </div>
        </article>


      </div>



      <div class="col-md-3" data-v-product>

        <article class="single-product-wrapper">
          <!-- Product Image -->
          <a href="#" data-v-product-url> </a>
          <div class="product-image">
            <a href="#" data-v-product-url>

              <img src="${Astero.builderAssetsUrl}/images/demo/product.jpg" loading="lazy" data-v-product-alt alt="" data-v-size="thumb"  data-v-product-image />

              <!-- Hover Thumb -->
              <img class="hover-img" src="${Astero.builderAssetsUrl}/images/demo/product-2.jpg" loading="lazy" data-v-product-alt alt=""  data-v-size="thumb" data-v-product-image-1 />
            </a>

            <!-- Favourite -->
            <div class="product-favourite">
              <a href="#" class="la la-heart" data-v-astero-action="addToWishlist" data-v-product-add_wishlist_url>
                <span></span>
              </a>
            </div>

            <!-- Compare -->
            <div class="product-compare">
              <a href="#" class="la la-random" data-v-astero-action="addToCompare" data-v-product-add_compare_url>
                <span></span>
              </a>
            </div>
          </div>

          <!-- Product Description -->
          <div class="product-content">

            <a href="#" class="text-body" data-v-product-url>
              <span data-v-product-name>Product 8</span>
            </a>

            <p class="product-price" data-v-if="_product.price > 0" data-v-product-price_tax_formatted>100.0000</p>

            <!-- Hover Content -->
            <div class="hover-content" data-v-if="_product.price > 0">
              <!-- Add to Cart -->
              <div class="add-to-cart-btn">
                <input type="hidden" name="product_id" value="" data-v-product-product_id />
                <a href="" class="btn btn-primary w-100" data-v-product-add_cart_url data-v-astero-action="addToCart" data-product_id="1">
                  <span class="loading d-none">
                    <span class="spinner-border spinner-border-sm align-middle" role="status" aria-hidden="true"> </span>
                    <span>Add to cart</span>...
                  </span>

                  <span class="button-text">
                    Add to cart
                  </span>
                </a>
              </div>
            </div>
          </div>
        </article>


      </div>



      <div class="col-md-3" data-v-product>

        <article class="single-product-wrapper">
          <!-- Product Image -->
          <a href="#" data-v-product-url> </a>
          <div class="product-image">
            <a href="#" data-v-product-url>

              <img src="${Astero.builderAssetsUrl}/images/demo/product.jpg" loading="lazy" data-v-product-alt alt="" data-v-size="thumb"  data-v-product-image />

              <!-- Hover Thumb -->
              <img class="hover-img" src="${Astero.builderAssetsUrl}/images/demo/product-2.jpg" loading="lazy" data-v-product-alt alt=""  data-v-size="thumb" data-v-product-image-1 />
            </a>

            <!-- Favourite -->
            <div class="product-favourite">
              <a href="#" class="la la-heart" data-v-astero-action="addToWishlist" data-v-product-add_wishlist_url>
                <span></span>
              </a>
            </div>

            <!-- Compare -->
            <div class="product-compare">
              <a href="#" class="la la-random" data-v-astero-action="addToCompare" data-v-product-add_compare_url>
                <span></span>
              </a>
            </div>
          </div>

          <!-- Product Description -->
          <div class="product-content">

            <a href="#" class="text-body" data-v-product-url>
              <span data-v-product-name>Product 8</span>
            </a>

            <p class="product-price" data-v-if="_product.price > 0" data-v-product-price_tax_formatted>100.0000</p>

            <!-- Hover Content -->
            <div class="hover-content" data-v-if="_product.price > 0">
              <!-- Add to Cart -->
              <div class="add-to-cart-btn">
                <input type="hidden" name="product_id" value="" data-v-product-product_id />
                <a href="" class="btn btn-primary w-100" data-v-product-add_cart_url data-v-astero-action="addToCart" data-product_id="1">
                  <span class="loading d-none">
                    <span class="spinner-border spinner-border-sm align-middle" role="status" aria-hidden="true"> </span>
                    <span>Add to cart</span>...
                  </span>

                  <span class="button-text">
                    Add to cart
                  </span>
                </a>
              </div>
            </div>
          </div>
        </article>


      </div>



    </div>
  </div>
</section>`,
});
Astero.SectionsGroup['Products'] = ['products/products-1'];
