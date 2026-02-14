<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { ref, computed, onMounted, watch, onUnmounted } from 'vue'
import { ShoppingCart, X, Plus, Minus, ArrowRight, Phone, MapPin, User, Truck, Loader2, ChevronLeft, ChevronRight } from 'lucide-vue-next'

// ─── Props ──────────────────────────────────────────────────────────────────
const props = defineProps<{
  products?: any[]
  categories?: any[]
}>()

const page = usePage()

// ─── Flash / Order Success ──────────────────────────────────────────────────
interface OrderSuccessData {
  uuid: string
  amount: number
  stk_sent: boolean
  stk_message: string
  checkout_request_id: string | null
}

const orderSuccess = ref<OrderSuccessData | null>(null)
const paymentStatus = ref<'pending' | 'completed' | 'failed' | null>(null)
const mpesaReceipt = ref<string | null>(null)
const isPolling = ref(false)

onMounted(() => {
  const flash = (page.props as any).flash
  if (flash?.orderSuccess) {
    orderSuccess.value = flash.orderSuccess
  }
})

// Watch for order success and start polling
watch(orderSuccess, (value) => {
  if (value?.stk_sent && value?.checkout_request_id) {
    paymentStatus.value = 'pending'
    pollPaymentStatus(value.checkout_request_id)
  }
}, { immediate: true })

// ─── Payment Status Polling ─────────────────────────────────────────────────
const pollPaymentStatus = (checkoutRequestId: string) => {
  isPolling.value = true
  let attempts = 0
  const maxAttempts = 40 // 2 minutes (40 * 3 seconds)

  const interval = setInterval(async () => {
    attempts++

    try {
      const res = await fetch(`/api/mpesa/status/${checkoutRequestId}`)
      const data = await res.json()

      if (data.status === 'completed') {
        clearInterval(interval)
        isPolling.value = false
        paymentStatus.value = 'completed'
        mpesaReceipt.value = data.mpesa_receipt
      } else if (data.status === 'failed') {
        clearInterval(interval)
        isPolling.value = false
        paymentStatus.value = 'failed'
      }
    } catch (e) {
      console.error('Poll error', e)
    }

    if (attempts >= maxAttempts) {
      clearInterval(interval)
      isPolling.value = false
    }
  }, 3000)
}

// ─── State ──────────────────────────────────────────────────────────────────
const activeCategory = ref<string | number>('all')
const selectedProduct = ref<any>(null)
const isCartOpen = ref(false)
const isCheckoutOpen = ref(false)
const isSuccessOpen = ref(false)

// ─── Cart ───────────────────────────────────────────────────────────────────
interface CartItem {
  product: any
  size: string
  color: string
  quantity: number
}

const cart = ref<CartItem[]>([])

const cartTotal = computed(() =>
    cart.value.reduce((sum, item) => sum + item.product.price * item.quantity, 0)
)

const cartCount = computed(() =>
    cart.value.reduce((sum, item) => sum + item.quantity, 0)
)

// ─── Filtered Products ──────────────────────────────────────────────────────
const filteredProducts = computed(() => {
  if (!props.products) return []
  if (activeCategory.value === 'all') return props.products
  return props.products.filter((p: any) => p.category_id === activeCategory.value)
})

// ─── Product Detail State ───────────────────────────────────────────────────
const selectedSize = ref<string>('')
const selectedColor = ref<string>('')
const detailQuantity = ref(1)

// ─── Image Gallery State ────────────────────────────────────────────────────
const currentImageIndex = ref(0)
const isZoomed = ref(false)
const mouseX = ref(0)
const mouseY = ref(0)
const isHovering = ref(false)
const zoomLensSize = 150
const zoomFactor = 2

const productImages = computed(() => {
  if (!selectedProduct.value?.images?.length) return []
  return selectedProduct.value.images.map((img: any) => {
    const path = img.path
    return path.startsWith('uploads/') ? '/' + path : '/' + path.replace('products/', 'uploads/')
  })
})

const currentImage = computed(() => {
  return productImages.value[currentImageIndex.value] || null
})

const hasMultipleImages = computed(() => {
  return productImages.value.length > 1
})

// Zoom styles
const mainImageStyle = computed(() => {
  if (!isZoomed.value || !isHovering.value) return {}

  const x = (mouseX.value / 100) * -50
  const y = (mouseY.value / 100) * -50

  return {
    transform: `scale(${zoomFactor}) translate(${x}px, ${y}px)`,
    transition: 'transform 0.1s ease-out',
    cursor: 'zoom-in'
  }
})

const zoomLensStyle = computed(() => {
  if (!isZoomed.value || !isHovering.value) return { display: 'none' }

  return {
    position: 'absolute',
    width: `${zoomLensSize}px`,
    height: `${zoomLensSize}px`,
    left: `${mouseX.value}%`,
    top: `${mouseY.value}%`,
    transform: 'translate(-50%, -50%)',
    backgroundColor: 'rgba(255, 255, 255, 0.3)',
    border: '2px solid #fff',
    borderRadius: '50%',
    pointerEvents: 'none',
    boxShadow: '0 0 10px rgba(0,0,0,0.2)',
    zIndex: 10
  }
})

const handleImageMouseMove = (e: MouseEvent) => {
  if (!isZoomed.value || !isHovering.value) return

  const rect = (e.currentTarget as HTMLElement).getBoundingClientRect()
  const x = ((e.clientX - rect.left) / rect.width) * 100
  const y = ((e.clientY - rect.top) / rect.height) * 100

  // Constrain values to prevent edge issues
  mouseX.value = Math.min(100, Math.max(0, x))
  mouseY.value = Math.min(100, Math.max(0, y))
}

const handleImageMouseEnter = () => {
  isHovering.value = true
}

const handleImageMouseLeave = () => {
  isHovering.value = false
  mouseX.value = 0
  mouseY.value = 0
}

const toggleZoom = () => {
  isZoomed.value = !isZoomed.value
  if (!isZoomed.value) {
    mouseX.value = 0
    mouseY.value = 0
    isHovering.value = false
  }
}

const prevImage = () => {
  if (currentImageIndex.value > 0) {
    currentImageIndex.value--
    resetZoom()
  }
}

const nextImage = () => {
  if (currentImageIndex.value < productImages.value.length - 1) {
    currentImageIndex.value++
    resetZoom()
  }
}

const selectImage = (index: number) => {
  currentImageIndex.value = index
  resetZoom()
}

const resetZoom = () => {
  isZoomed.value = false
  isHovering.value = false
  mouseX.value = 0
  mouseY.value = 0
}

const openProductDetail = (product: any) => {
  selectedProduct.value = product
  selectedSize.value = product.sizes?.[0] || ''
  selectedColor.value = product.colors?.[0] || ''
  detailQuantity.value = 1
  currentImageIndex.value = 0
  resetZoom()
  // Prevent body scroll when modal is open
  document.body.style.overflow = 'hidden'
}

const closeProductDetail = () => {
  selectedProduct.value = null
  currentImageIndex.value = 0
  resetZoom()
  // Restore body scroll
  document.body.style.overflow = 'auto'
}

// Reset when product changes
watch(selectedProduct, () => {
  currentImageIndex.value = 0
  resetZoom()
})

// ─── Add to Cart ────────────────────────────────────────────────────────────
const addToCart = () => {
  if (!selectedProduct.value || !selectedSize.value || !selectedColor.value) return

  const existing = cart.value.find(
      (item) =>
          item.product.id === selectedProduct.value.id &&
          item.size === selectedSize.value &&
          item.color === selectedColor.value
  )

  if (existing) {
    existing.quantity += detailQuantity.value
  } else {
    cart.value.push({
      product: selectedProduct.value,
      size: selectedSize.value,
      color: selectedColor.value,
      quantity: detailQuantity.value,
    })
  }

  closeProductDetail()
}

const removeFromCart = (index: number) => {
  cart.value.splice(index, 1)
}

const updateQuantity = (index: number, delta: number) => {
  const newQty = cart.value[index].quantity + delta
  if (newQty < 1) {
    removeFromCart(index)
  } else {
    cart.value[index].quantity = newQty
  }
}

// ─── Checkout Form ──────────────────────────────────────────────────────────
const orderForm = useForm({
  customer_name: '',
  mpesa_number: '',
  town: '',
  description: '',
  items: [] as any[],
})

const openCheckout = () => {
  isCartOpen.value = false
  isCheckoutOpen.value = true
  document.body.style.overflow = 'hidden'
}

const closeCheckout = () => {
  isCheckoutOpen.value = false
  document.body.style.overflow = 'auto'
}

const submitOrder = () => {
  orderForm.items = cart.value.map((item) => ({
    product_id: item.product.id,
    size: item.size,
    color: item.color,
    quantity: item.quantity,
    price: item.product.price,
  }))

  orderForm.post('/shop/order', {
    preserveScroll: true,
    onSuccess: () => {
      isCheckoutOpen.value = false
      isSuccessOpen.value = true
      cart.value = []
      orderForm.reset()
      document.body.style.overflow = 'hidden'
    },
    onError: () => {
      // errors shown inline
    },
  })
}

// ─── Close Success Modal ────────────────────────────────────────────────────
const closeSuccessModal = () => {
  isSuccessOpen.value = false
  orderSuccess.value = null
  paymentStatus.value = null
  mpesaReceipt.value = null
  document.body.style.overflow = 'auto'
}

// ─── Parallax Effect ────────────────────────────────────────────────────────
const scrollY = ref(0)

const handleScroll = () => {
  scrollY.value = window.scrollY
}

onMounted(() => {
  window.addEventListener('scroll', handleScroll, { passive: true })
  if (orderSuccess.value) {
    isSuccessOpen.value = true
    document.body.style.overflow = 'hidden'
  }
})

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll)
  document.body.style.overflow = 'auto'
})

const parallaxBgStyle = computed(() => ({
  transform: `translateY(${scrollY.value * 0.5}px) scale(${1 + scrollY.value * 0.0005})`,
}))

const heroContentStyle = computed(() => ({
  transform: `translateY(${scrollY.value * -0.2}px)`,
  opacity: Math.max(0, 1 - scrollY.value / 600),
}))

const heroOverlayStyle = computed(() => ({
  opacity: Math.min(0.8, 0.6 + scrollY.value / 1000),
}))

// ─── Helpers ────────────────────────────────────────────────────────────────
const formatPrice = (price: number) => `KES ${Number(price).toLocaleString()}`

const getProductImage = (product: any) => {
  if (product.images && product.images.length > 0) {
    const path = product.images[0].path;
    if (path.startsWith('uploads/')) {
      return '/' + path;
    }
    return '/' + path.replace('products/', 'uploads/');
  }
  return null
}
</script>

<template>
  <Head title="Shop" />

  <!-- ─── PAGE ──────────────────────────────────────────────────────────── -->
  <div class="app-container">
    <!-- NAV -->
    <nav class="site-nav">
      <div class="nav-container">
        <h1 class="logo">
          <span class="logo-accent">D</span>rmorch
        </h1>
        <button @click="isCartOpen = true" class="cart-button">
          <ShoppingCart :size="22" />
          <span v-if="cartCount > 0" class="cart-badge">
            {{ cartCount > 9 ? '9+' : cartCount }}
          </span>
        </button>
      </div>
    </nav>

    <!-- HERO WITH PARALLAX -->
    <section class="hero-section">
      <div class="hero-bg" :style="parallaxBgStyle"></div>
      <div class="hero-overlay" :style="heroOverlayStyle"></div>

      <div class="floating-shape shape-1" :style="{ transform: `translate(${scrollY * 0.1}px, ${scrollY * -0.15}px)` }"></div>
      <div class="floating-shape shape-2" :style="{ transform: `translate(${scrollY * -0.08}px, ${scrollY * 0.1}px)` }"></div>

      <div class="hero-content" :style="heroContentStyle">
        <div>
          <p class="hero-subtitle">New Collection 2026</p>
          <h2 class="hero-title">
            Walk with<br><span class="hero-highlight">confidence.</span>
          </h2>
          <p class="hero-description">
            Premium footwear crafted for those who value style, comfort and every step they take.
          </p>
          <button class="btn-primary hero-cta" @click="document.getElementById('products-section')?.scrollIntoView({ behavior: 'smooth' })">
            Shop Now <ArrowRight :size="16" />
          </button>
        </div>
      </div>

      <div class="hero-accent-line"></div>
      <div class="scroll-indicator" :style="{ opacity: Math.max(0, 1 - scrollY / 150) }">
        <div class="scroll-arrow"></div>
      </div>
    </section>

    <!-- CATEGORY FILTER -->
    <section class="category-section">
      <div class="category-container">
        <span
            class="category-pill"
            :class="{ active: activeCategory === 'all' }"
            @click="activeCategory = 'all'"
        >All</span>
        <span
            v-for="cat in categories"
            :key="cat.id"
            class="category-pill"
            :class="{ active: activeCategory === cat.id }"
            @click="activeCategory = cat.id"
        >
          {{ cat.name }}
          <span v-if="cat.products_count" class="category-count">({{ cat.products_count }})</span>
        </span>
      </div>
    </section>

    <!-- PRODUCT GRID -->
    <section id="products-section" class="products-section">
      <div v-if="filteredProducts.length === 0" class="empty-state">
        No products in this category yet.
      </div>
      <div class="product-grid">
        <article
            v-for="product in filteredProducts"
            :key="product.id"
            @click="openProductDetail(product)"
            class="product-card"
        >
          <div class="product-image-container">
            <img
                v-if="getProductImage(product)"
                :src="getProductImage(product)"
                :alt="product.name"
                class="product-image"
            />
            <div v-else class="product-image-placeholder">
              <ShoppingCart :size="32" />
            </div>
            <span class="product-category-badge">
              {{ product.category?.name }}
            </span>
          </div>
          <div class="product-info">
            <h3 class="product-title">{{ product.name }}</h3>
            <div class="product-meta">
              <span class="product-price">{{ formatPrice(product.price) }}</span>
              <span class="product-options">
                {{ (product.colors || []).length }} colors · {{ (product.sizes || []).length }} sizes
              </span>
            </div>
          </div>
        </article>
      </div>
    </section>

    <!-- WHATSAPP FLOATING BUTTON -->
    <a href="https://wa.me/254700801438?text=Hi%20DR.Morch%20Crafts%2C%20I%27d%20like%20to%20inquire%20about%20your%20shoes."
       target="_blank"
       rel="noopener noreferrer"
       class="whatsapp-fab"
       title="Chat with us on WhatsApp"
    >
      <svg viewBox="0 0 24 24" width="28" height="28" fill="white">
        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
      </svg>
    </a>

    <!-- FOOTER -->
    <footer class="site-footer">
      <p class="footer-logo">
        <span class="logo-accent">D</span>rmorch
      </p>
      <p class="footer-brand">DR.MORCH CRAFTS</p>
      <p class="footer-address">
        Ground Room, 3 Ampel House, Ground Floor, DR.Morch Workshop,<br>
        Lurambi-Sichirai Street off Kakamega Webuye Road, Kakamega
      </p>
      <p class="footer-phone">Tel: 0700 801 438</p>
      <p class="footer-reg">Business Reg: BN-RRS3LV6P</p>
      <p class="footer-copyright">© 2026 DR.Morch Crafts. All rights reserved.</p>
    </footer>
  </div>

  <!-- PRODUCT DETAIL MODAL WITH RESPONSIVE IMAGE GALLERY -->
  <div v-if="selectedProduct" class="modal-overlay" @click.self="closeProductDetail">
    <div class="product-modal">
      <button @click="closeProductDetail" class="modal-close-btn">
        <X :size="18" />
      </button>

      <div class="product-modal-content">
        <!-- Image Gallery Section - Responsive -->
        <div class="gallery-section">
          <div class="gallery-main-container">
            <div
                class="gallery-main-wrapper"
                @mouseenter="handleImageMouseEnter"
                @mouseleave="handleImageMouseLeave"
                @mousemove="handleImageMouseMove"
                @click="toggleZoom"
                @touchstart="toggleZoom"
            >
              <img
                  v-if="currentImage"
                  :src="currentImage"
                  :alt="selectedProduct.name"
                  class="gallery-main-image"
                  :class="{ 'zoomed': isZoomed }"
                  :style="isZoomed ? mainImageStyle : {}"
              />
              <div v-else class="gallery-main-placeholder">
                <ShoppingCart :size="48" />
              </div>

              <!-- Zoom Lens - Hidden on mobile -->
              <div v-if="isZoomed && windowWidth > 768" class="zoom-lens" :style="zoomLensStyle"></div>

              <!-- Zoom Hint - Hidden on mobile -->
              <div v-if="!isZoomed && hasMultipleImages && windowWidth > 768" class="zoom-hint">
                <span>Click to zoom</span>
              </div>
            </div>

            <!-- Navigation Arrows - Hidden on mobile -->
            <button
                v-if="hasMultipleImages && currentImageIndex > 0 && windowWidth > 768"
                class="gallery-nav-arrow prev"
                @click="prevImage"
            >
              <ChevronLeft :size="20" />
            </button>
            <button
                v-if="hasMultipleImages && currentImageIndex < productImages.length - 1 && windowWidth > 768"
                class="gallery-nav-arrow next"
                @click="nextImage"
            >
              <ChevronRight :size="20" />
            </button>

            <!-- Image Counter -->
            <div v-if="hasMultipleImages" class="gallery-counter">
              {{ currentImageIndex + 1 }} / {{ productImages.length }}
            </div>

            <!-- Category Badge -->
            <span class="product-category-badge">{{ selectedProduct.category?.name }}</span>

            <!-- Mobile Navigation Dots -->
            <div v-if="hasMultipleImages && windowWidth <= 768" class="mobile-nav-dots">
              <button class="mobile-nav-btn prev" @click="prevImage" :disabled="currentImageIndex === 0">
                <ChevronLeft :size="20" />
              </button>
              <div class="dot-indicators">
                <span
                    v-for="(_, index) in productImages"
                    :key="index"
                    class="dot"
                    :class="{ active: currentImageIndex === index }"
                    @click="selectImage(index)"
                ></span>
              </div>
              <button class="mobile-nav-btn next" @click="nextImage" :disabled="currentImageIndex === productImages.length - 1">
                <ChevronRight :size="20" />
              </button>
            </div>
          </div>

          <!-- Thumbnail Strip - Hidden on mobile -->
          <div v-if="hasMultipleImages && windowWidth > 768" class="gallery-thumbnails">
            <div
                v-for="(image, index) in productImages"
                :key="index"
                class="gallery-thumbnail"
                :class="{ 'active': currentImageIndex === index }"
                @click="selectImage(index)"
            >
              <img :src="image" :alt="`${selectedProduct.name} ${index + 1}`" />
            </div>
          </div>
        </div>

        <!-- Product Details Section -->
        <div class="details-section">
          <!-- Breadcrumb - Responsive -->
          <div class="breadcrumb">
            <span>Home</span>
            <ArrowRight :size="12" />
            <span>Fashion</span>
            <ArrowRight :size="12" />
            <span>MEN</span>
          </div>

          <!-- Header - Responsive -->
          <div class="product-header">
            <h2 class="product-name">{{ selectedProduct.name }}</h2>
            <div class="product-price-large">{{ formatPrice(selectedProduct.price) }}</div>
          </div>

          <!-- Description - Responsive -->
          <p class="product-description">{{ selectedProduct.description || 'Test from the phone' }}</p>

          <!-- Phone order description -->
          <p class="order-description">Phone order description</p>

          <!-- Color Selection - Responsive -->
          <div v-if="selectedProduct.colors && selectedProduct.colors.length" class="product-section">
            <h3 class="section-title">COLOR</h3>
            <div class="color-pills">
              <button
                  v-for="color in selectedProduct.colors"
                  :key="color"
                  class="color-pill"
                  :class="{ 'selected': selectedColor === color }"
                  @click="selectedColor = color"
              >
                <span class="color-dot" :style="{ backgroundColor: color.toLowerCase() }"></span>
                {{ color }}
              </button>
            </div>
          </div>

          <!-- Size Selection - Responsive -->
          <div v-if="selectedProduct.sizes && selectedProduct.sizes.length" class="product-section">
            <h3 class="section-title">SIZE</h3>
            <div class="size-pills">
              <button
                  v-for="size in selectedProduct.sizes"
                  :key="size"
                  class="size-pill"
                  :class="{ 'selected': selectedSize === size }"
                  @click="selectedSize = size"
              >
                {{ size }}
              </button>
            </div>
          </div>

          <!-- Stock Status - Responsive -->
          <div class="stock-status" :class="{ 'low': selectedProduct.stock < 10 }">
            <span class="stock-dot"></span>
            {{ selectedProduct.stock > 0 ? `${selectedProduct.stock} in stock` : 'Out of stock' }}
          </div>

          <!-- Quantity & Add to Cart - Responsive -->
          <div class="product-actions">
            <div class="quantity-wrapper">
              <button
                  class="quantity-btn"
                  @click="detailQuantity = Math.max(1, detailQuantity - 1)"
                  :disabled="detailQuantity <= 1"
              >
                <Minus :size="16" />
              </button>
              <span class="quantity-value">{{ detailQuantity }}</span>
              <button
                  class="quantity-btn"
                  @click="detailQuantity = Math.min(selectedProduct.stock, detailQuantity + 1)"
                  :disabled="detailQuantity >= selectedProduct.stock"
              >
                <Plus :size="16" />
              </button>
            </div>

            <button
                class="add-to-cart-btn"
                :disabled="!selectedSize || !selectedColor || selectedProduct.stock === 0"
                @click="addToCart"
            >
              <ShoppingCart :size="18" />
              Add to Cart
              <span class="cart-total">{{ formatPrice(selectedProduct.price * detailQuantity) }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- CART DRAWER - Responsive -->
  <div v-if="isCartOpen" class="modal-overlay side-right" @click.self="isCartOpen = false">
    <div class="cart-drawer">
      <div class="cart-header">
        <h2 class="cart-title">Your Cart</h2>
        <button @click="isCartOpen = false" class="cart-close-btn">
          <X :size="20" />
        </button>
      </div>

      <div class="cart-items">
        <div v-if="cart.length === 0" class="cart-empty">
          <ShoppingCart :size="36" />
          <p>Your cart is empty</p>
        </div>
        <div v-for="(item, index) in cart" :key="index" class="cart-item">
          <div class="cart-item-image">
            <img v-if="getProductImage(item.product)" :src="getProductImage(item.product)" />
            <div v-else><ShoppingCart :size="18" /></div>
          </div>
          <div class="cart-item-details">
            <p class="cart-item-name">{{ item.product.name }}</p>
            <p class="cart-item-options">{{ item.color }} · Size {{ item.size }}</p>
            <div class="cart-item-actions">
              <div class="cart-item-quantity">
                <button class="qty-btn-small" @click="updateQuantity(index, -1)">
                  <Minus :size="12" />
                </button>
                <span>{{ item.quantity }}</span>
                <button class="qty-btn-small" @click="updateQuantity(index, 1)">
                  <Plus :size="12" />
                </button>
              </div>
              <span class="cart-item-price">{{ formatPrice(item.product.price * item.quantity) }}</span>
            </div>
          </div>
          <button @click="removeFromCart(index)" class="cart-item-remove" title="Remove">
            <X :size="16" />
          </button>
        </div>
      </div>

      <div v-if="cart.length > 0" class="cart-footer">
        <div class="cart-total-row">
          <span>Total</span>
          <span class="cart-total-amount">{{ formatPrice(cartTotal) }}</span>
        </div>
        <button class="btn-primary cart-checkout-btn" @click="openCheckout">
          Proceed to Checkout <ArrowRight :size="16" />
        </button>
      </div>
    </div>
  </div>

  <!-- CHECKOUT MODAL - Responsive -->
  <div v-if="isCheckoutOpen" class="modal-overlay" @click.self="closeCheckout">
    <div class="checkout-modal">
      <button @click="closeCheckout" class="checkout-close-btn">
        <X :size="20" />
      </button>

      <div class="checkout-content">
        <h2 class="checkout-title">Checkout</h2>
        <p class="checkout-subtitle">Fill in your details and we'll send an M-Pesa prompt to your phone.</p>

        <div class="order-summary">
          <div v-for="(item, i) in cart" :key="i" class="order-item">
            <span>{{ item.product.name }} <span class="order-item-options">({{ item.color }}, {{ item.size }}) x{{ item.quantity }}</span></span>
            <span class="order-item-price">{{ formatPrice(item.product.price * item.quantity) }}</span>
          </div>
          <div class="order-total">
            <span>Total</span>
            <span>{{ formatPrice(cartTotal) }}</span>
          </div>
        </div>

        <div class="checkout-form">
          <div class="form-group">
            <label class="form-label"><User :size="12" />Name</label>
            <input v-model="orderForm.customer_name" class="form-input" :class="{ error: orderForm.errors.customer_name }" placeholder="e.g. John Doe" />
            <span v-if="orderForm.errors.customer_name" class="form-error">{{ orderForm.errors.customer_name }}</span>
          </div>
          <div class="form-group">
            <label class="form-label"><Phone :size="12" />M-Pesa No</label>
            <input v-model="orderForm.mpesa_number" class="form-input" :class="{ error: orderForm.errors.mpesa_number }" placeholder="e.g. 0712345678" type="tel" />
            <span v-if="orderForm.errors.mpesa_number" class="form-error">{{ orderForm.errors.mpesa_number }}</span>
          </div>
          <div class="form-group full-width">
            <label class="form-label"><MapPin :size="12" />Town</label>
            <input v-model="orderForm.town" class="form-input" :class="{ error: orderForm.errors.town }" placeholder="e.g. Nairobi" />
            <span v-if="orderForm.errors.town" class="form-error">{{ orderForm.errors.town }}</span>
          </div>
          <div class="form-group full-width">
            <label class="form-label"><Truck :size="12" />Delivery / Pickup Details</label>
            <textarea v-model="orderForm.description" class="form-input" :class="{ error: orderForm.errors.description }" placeholder="Describe your delivery address or pickup point..." rows="2"></textarea>
            <span v-if="orderForm.errors.description" class="form-error">{{ orderForm.errors.description }}</span>
          </div>
        </div>

        <p class="payment-note">
          An M-Pesa payment request of <strong>{{ formatPrice(cartTotal) }}</strong> will be sent to your phone immediately after placing the order.
        </p>

        <button
            class="btn-primary checkout-submit"
            :disabled="orderForm.processing"
            @click="submitOrder"
        >
          <Loader2 v-if="orderForm.processing" :size="16" class="animate-spin" />
          {{ orderForm.processing ? 'Placing Order...' : 'Place Order & Pay' }} <ArrowRight v-if="!orderForm.processing" :size="16" />
        </button>
      </div>
    </div>
  </div>

  <!-- ORDER SUCCESS MODAL - Responsive -->
  <div v-if="isSuccessOpen" class="modal-overlay">
    <div class="success-modal">
      <!-- Pending Payment -->
      <template v-if="paymentStatus === 'pending'">
        <div class="success-icon pending">
          <Loader2 :size="32" class="animate-spin" />
        </div>
        <h2 class="success-title">Waiting for Payment</h2>
        <p class="success-message">
          {{ orderSuccess?.stk_message || 'Check your phone for the M-Pesa prompt and enter your PIN to complete payment.' }}
        </p>
        <div class="success-amount">
          <p class="amount-label">Amount</p>
          <p class="amount-value">{{ formatPrice(orderSuccess?.amount || 0) }}</p>
        </div>
        <p class="status-checking">
          <Loader2 :size="12" class="animate-spin" />
          Checking payment status...
        </p>
      </template>

      <!-- Payment Successful -->
      <template v-else-if="paymentStatus === 'completed'">
        <div class="success-icon success">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#2ecc71" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
        </div>
        <h2 class="success-title">Payment Successful!</h2>
        <p class="success-message">Thank you for your purchase. Your order is being processed.</p>
        <div class="success-receipt">
          <p class="receipt-label">M-Pesa Receipt</p>
          <p class="receipt-value">{{ mpesaReceipt }}</p>
        </div>
        <div v-if="orderSuccess" class="success-reference">
          <p class="reference-label">Order Reference</p>
          <p class="reference-value">{{ orderSuccess.uuid }}</p>
        </div>
        <button class="btn-primary success-btn" @click="closeSuccessModal">
          Continue Shopping
        </button>
      </template>

      <!-- Payment Failed -->
      <template v-else-if="paymentStatus === 'failed'">
        <div class="success-icon failed">
          <X :size="32" />
        </div>
        <h2 class="success-title">Payment Failed</h2>
        <p class="success-message">
          The payment was not completed. This could be due to insufficient funds, wrong PIN, or cancelled request.
        </p>
        <div v-if="orderSuccess" class="success-reference">
          <p class="reference-label">Order Reference</p>
          <p class="reference-value">{{ orderSuccess.uuid }}</p>
          <p class="reference-help">Contact us with this reference to retry payment.</p>
        </div>
        <button class="btn-outline success-btn" @click="closeSuccessModal">
          Close
        </button>
      </template>

      <!-- Initial Success -->
      <template v-else>
        <div class="success-icon success">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#2ecc71" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
        </div>
        <h2 class="success-title">Order Placed!</h2>
        <p class="success-message">
          {{ orderSuccess?.stk_sent ? orderSuccess.stk_message : 'Your order has been received. We will contact you shortly regarding payment.' }}
        </p>
        <div v-if="orderSuccess" class="success-reference">
          <p class="reference-label">Order Reference</p>
          <p class="reference-value">{{ orderSuccess.uuid }}</p>
        </div>
        <button class="btn-outline success-btn" @click="closeSuccessModal">
          Continue Shopping
        </button>
      </template>
    </div>
  </div>
</template>

<style>
/* Import fonts */
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap');

/* CSS Variables */
:root {
  --cream: #f5f0eb;
  --cream-dark: #ebe5dc;
  --charcoal: #1a1a1a;
  --charcoal-soft: #3a3a3a;
  --accent: #c8a96e;
  --accent-light: #dcc49a;
  --accent-dark: #a07b3e;
  --text-muted: #7a7168;
  --white: #ffffff;
  --shadow-sm: 0 2px 8px rgba(0,0,0,0.05);
  --shadow-md: 0 4px 12px rgba(0,0,0,0.1);
  --shadow-lg: 0 8px 24px rgba(0,0,0,0.15);
  --transition: all 0.2s ease;
}

/* Base Styles */
* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

body {
  font-family: 'Inter', sans-serif;
  background: var(--cream);
  color: var(--charcoal);
  -webkit-font-smoothing: antialiased;
  overflow-x: hidden;
}

.app-container {
  min-height: 100vh;
  background: var(--cream);
}

/* Typography */
.font-display {
  font-family: 'Playfair Display', serif;
}

/* Scrollbar */
::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}
::-webkit-scrollbar-track {
  background: transparent;
}
::-webkit-scrollbar-thumb {
  background: var(--accent-light);
  border-radius: 3px;
}

/* Navigation */
.site-nav {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 40;
  background: rgba(245,240,235,0.92);
  backdrop-filter: blur(8px);
  border-bottom: 1px solid #e0d9d0;
}

.nav-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 24px;
  height: 64px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.logo {
  font-family: 'Playfair Display', serif;
  font-size: 1.5rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: var(--charcoal);
}

.logo-accent {
  color: var(--accent);
}

.cart-button {
  position: relative;
  background: none;
  border: none;
  cursor: pointer;
  padding: 8px;
  color: var(--charcoal);
}

.cart-badge {
  position: absolute;
  top: 0;
  right: 0;
  background: var(--charcoal);
  color: white;
  font-size: 0.6rem;
  font-weight: 700;
  width: 17px;
  height: 17px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid var(--cream);
}

/* Hero Section */
.hero-section {
  position: relative;
  overflow: hidden;
  height: 500px;
  margin-top: 64px;
}

.hero-bg {
  position: absolute;
  inset: -50px;
  background: url('/images/auth-bg.jpg') center/cover no-repeat;
  will-change: transform;
}

.hero-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(26,26,26,0.7) 0%, rgba(26,26,26,0.4) 100%);
  will-change: opacity;
}

.floating-shape {
  position: absolute;
  border-radius: 50%;
  opacity: 0.08;
  pointer-events: none;
  will-change: transform;
}

.floating-shape.shape-1 {
  width: 300px;
  height: 300px;
  background: var(--accent);
  top: -50px;
  right: 10%;
  filter: blur(60px);
}

.floating-shape.shape-2 {
  width: 200px;
  height: 200px;
  background: white;
  bottom: 20%;
  left: 5%;
  filter: blur(40px);
}

.hero-content {
  position: relative;
  z-index: 1;
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 24px;
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  will-change: transform, opacity;
}

.hero-subtitle {
  font-size: 0.7rem;
  font-weight: 500;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--accent);
  margin-bottom: 16px;
}

.hero-title {
  font-family: 'Playfair Display', serif;
  font-size: clamp(2rem, 5vw, 4rem);
  font-weight: 700;
  color: white;
  line-height: 1.1;
  max-width: 560px;
}

.hero-highlight {
  color: var(--accent);
}

.hero-description {
  margin-top: 20px;
  color: rgba(255,255,255,0.75);
  font-size: 0.95rem;
  max-width: 420px;
  line-height: 1.7;
}

.hero-cta {
  margin-top: 32px;
}

.hero-accent-line {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, var(--accent), transparent 70%);
  z-index: 2;
}

.scroll-indicator {
  position: absolute;
  bottom: 30px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 2;
  transition: opacity 0.3s;
}

.scroll-arrow {
  width: 24px;
  height: 24px;
  border-right: 2px solid rgba(255,255,255,0.5);
  border-bottom: 2px solid rgba(255,255,255,0.5);
  transform: rotate(45deg);
  animation: scrollBounce 2s infinite;
}

@keyframes scrollBounce {
  0%, 20%, 50%, 80%, 100% { transform: rotate(45deg) translateY(0); }
  40% { transform: rotate(45deg) translateY(8px); }
  60% { transform: rotate(45deg) translateY(4px); }
}

/* Category Section */
.category-section {
  max-width: 1200px;
  margin: 0 auto;
  padding: 40px 24px 0;
}

.category-container {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  align-items: center;
}

.category-pill {
  display: inline-block;
  font-size: 0.7rem;
  font-weight: 500;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  padding: 4px 10px;
  border-radius: 999px;
  background: var(--cream-dark);
  color: var(--charcoal-soft);
  cursor: pointer;
  border: 1px solid transparent;
  transition: var(--transition);
}

.category-pill:hover {
  border-color: var(--accent);
}

.category-pill.active {
  background: var(--charcoal);
  color: var(--cream);
  border-color: var(--charcoal);
}

.category-count {
  opacity: 0.5;
  margin-left: 4px;
}

/* Products Section */
.products-section {
  max-width: 1200px;
  margin: 0 auto;
  padding: 32px 24px 80px;
}

.empty-state {
  text-align: center;
  padding: 60px 0;
  color: var(--text-muted);
}

.product-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 24px;
}

.product-card {
  background: white;
  border-radius: 10px;
  overflow: hidden;
  cursor: pointer;
  border: 1px solid #ede8e2;
  transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s;
}

.product-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 12px 40px rgba(0,0,0,0.1);
}

.product-image-container {
  aspect-ratio: 4/3;
  background: var(--cream-dark);
  position: relative;
  overflow: hidden;
}

.product-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.4s ease;
}

.product-card:hover .product-image {
  transform: scale(1.05);
}

.product-image-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #c5bfb5;
}

.product-category-badge {
  position: absolute;
  top: 12px;
  left: 12px;
  background: rgba(255,255,255,0.9);
  backdrop-filter: blur(4px);
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 0.68rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--charcoal-soft);
}

.product-info {
  padding: 16px;
}

.product-title {
  font-size: 0.92rem;
  font-weight: 600;
  color: var(--charcoal);
  margin: 0 0 6px;
}

.product-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.product-price {
  font-size: 1rem;
  font-weight: 700;
  color: var(--charcoal);
}

.product-options {
  font-size: 0.7rem;
  color: var(--text-muted);
}

/* WhatsApp FAB */
.whatsapp-fab {
  position: fixed;
  bottom: 24px;
  right: 24px;
  z-index: 45;
  width: 56px;
  height: 56px;
  background: #25D366;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 16px rgba(37, 211, 102, 0.4);
  transition: transform 0.2s, box-shadow 0.2s;
  text-decoration: none;
}

.whatsapp-fab:hover {
  transform: scale(1.1);
  box-shadow: 0 6px 24px rgba(37, 211, 102, 0.5);
}

/* Footer */
.site-footer {
  background: var(--charcoal);
  color: rgba(255,255,255,0.5);
  padding: 40px 24px;
  text-align: center;
}

.footer-logo {
  font-family: 'Playfair Display', serif;
  font-size: 1.1rem;
  color: white;
  margin: 0 0 8px;
}

.footer-brand {
  font-size: 0.85rem;
  color: rgba(255,255,255,0.7);
  margin: 0 0 12px;
  font-weight: 500;
}

.footer-address {
  font-size: 0.72rem;
  color: rgba(255,255,255,0.5);
  margin: 0 0 8px;
  max-width: 400px;
  margin-left: auto;
  margin-right: auto;
  line-height: 1.5;
}

.footer-phone {
  font-size: 0.72rem;
  color: rgba(255,255,255,0.6);
  margin: 0 0 8px;
}

.footer-reg {
  font-size: 0.7rem;
  color: rgba(255,255,255,0.4);
  margin: 0 0 4px;
}

.footer-copyright {
  font-size: 0.72rem;
  margin: 0;
}

/* Modal Overlay */
.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 50;
  background: rgba(26,26,26,0.55);
  backdrop-filter: blur(2px);
  display: flex;
  align-items: center;
  justify-content: center;
  animation: fadeIn 0.2s ease;
}

.modal-overlay.side-right {
  align-items: stretch;
  justify-content: flex-end;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

/* Product Modal - Responsive */
.product-modal {
  background: white;
  border-radius: 16px;
  width: 90%;
  max-width: 1000px;
  max-height: 90vh;
  overflow: hidden;
  position: relative;
  animation: slideUp 0.28s cubic-bezier(.22,1,.36,1);
  box-shadow: 0 25px 60px rgba(0,0,0,0.25);
}

@keyframes slideUp {
  from { transform: translateY(24px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}

.product-modal-content {
  display: grid;
  grid-template-columns: 1fr 1fr;
  height: 100%;
  overflow-y: auto;
}

.modal-close-btn {
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 20;
  background: rgba(255,255,255,0.95);
  backdrop-filter: blur(8px);
  border: none;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 2px 12px rgba(0,0,0,0.15);
  transition: var(--transition);
  color: var(--charcoal);
}

.modal-close-btn:hover {
  transform: scale(1.08);
  box-shadow: 0 4px 16px rgba(0,0,0,0.2);
}

/* Gallery Section */
.gallery-section {
  padding: 24px;
  background: #faf8f5;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.gallery-main-container {
  position: relative;
  width: 100%;
  aspect-ratio: 1;
  border-radius: 16px;
  overflow: hidden;
  background: white;
  box-shadow: 0 4px 20px rgba(0,0,0,0.05);
}

.gallery-main-wrapper {
  width: 100%;
  height: 100%;
  position: relative;
  cursor: zoom-in;
  overflow: hidden;
}

.gallery-main-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.3s ease;
}

.gallery-main-image.zoomed {
  transition: transform 0.1s ease;
  cursor: grab;
}

.gallery-main-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #f0ebe5 0%, #e5dfd6 100%);
  color: #c5bfb5;
}

/* Zoom Elements */
.zoom-lens {
  position: absolute;
  width: 120px;
  height: 120px;
  border-radius: 50%;
  border: 2px solid white;
  background: rgba(255,255,255,0.3);
  box-shadow: 0 0 20px rgba(0,0,0,0.2);
  pointer-events: none;
  transition: all 0.05s ease;
  z-index: 10;
}

.zoom-hint {
  position: absolute;
  bottom: 16px;
  right: 16px;
  background: rgba(0,0,0,0.6);
  color: white;
  padding: 6px 14px;
  border-radius: 30px;
  font-size: 12px;
  backdrop-filter: blur(4px);
  z-index: 5;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 0.8; }
  50% { opacity: 0.5; }
}

/* Gallery Navigation */
.gallery-nav-arrow {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: white;
  border: none;
  box-shadow: 0 2px 12px rgba(0,0,0,0.15);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: var(--transition);
  z-index: 15;
  color: var(--charcoal);
}

.gallery-nav-arrow:hover {
  transform: translateY(-50%) scale(1.1);
  background: #f8f9fa;
  box-shadow: 0 4px 16px rgba(0,0,0,0.2);
}

.gallery-nav-arrow.prev {
  left: 16px;
}

.gallery-nav-arrow.next {
  right: 16px;
}

.gallery-counter {
  position: absolute;
  bottom: 16px;
  right: 16px;
  background: rgba(0,0,0,0.6);
  color: white;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 12px;
  backdrop-filter: blur(4px);
  z-index: 5;
}

/* Mobile Navigation Dots */
.mobile-nav-dots {
  position: absolute;
  bottom: 16px;
  left: 0;
  right: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  z-index: 15;
}

.mobile-nav-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: rgba(255,255,255,0.9);
  border: none;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--charcoal);
  transition: var(--transition);
}

.mobile-nav-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.dot-indicators {
  display: flex;
  gap: 8px;
}

.dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: rgba(255,255,255,0.5);
  transition: var(--transition);
}

.dot.active {
  width: 20px;
  background: white;
  border-radius: 10px;
}

/* Thumbnails */
.gallery-thumbnails {
  display: flex;
  gap: 12px;
  overflow-x: auto;
  padding: 4px 0 8px;
}

.gallery-thumbnail {
  width: 70px;
  height: 70px;
  border-radius: 10px;
  overflow: hidden;
  cursor: pointer;
  border: 2px solid transparent;
  transition: var(--transition);
  flex-shrink: 0;
  background: white;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.gallery-thumbnail:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.gallery-thumbnail.active {
  border-color: var(--accent);
  transform: scale(1.02);
}

.gallery-thumbnail img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* Details Section */
.details-section {
  padding: 32px;
  overflow-y: auto;
  background: white;
}

.breadcrumb {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--text-muted);
  font-size: 12px;
  margin-bottom: 24px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  flex-wrap: wrap;
}

.breadcrumb span:not(:last-child) {
  color: var(--accent);
  cursor: pointer;
}

.product-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 16px;
  gap: 20px;
  flex-wrap: wrap;
}

.product-name {
  font-size: clamp(20px, 4vw, 28px);
  font-weight: 700;
  color: var(--charcoal);
  line-height: 1.2;
  margin: 0;
  font-family: 'Playfair Display', serif;
}

.product-price-large {
  font-size: clamp(18px, 3vw, 24px);
  font-weight: 700;
  color: var(--charcoal);
  white-space: nowrap;
  background: var(--cream);
  padding: 8px 16px;
  border-radius: 12px;
}

.product-description {
  font-size: 14px;
  color: var(--text-muted);
  line-height: 1.7;
  margin-bottom: 16px;
}

.order-description {
  font-size: 13px;
  color: var(--text-muted);
  margin-bottom: 24px;
  padding-bottom: 16px;
  border-bottom: 1px solid #f0ebe5;
}

.product-section {
  margin-bottom: 24px;
}

.section-title {
  font-size: 12px;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin: 0 0 12px;
}

/* Color Pills */
.color-pills {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.color-pill {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border: 1.5px solid #e5dfd6;
  border-radius: 40px;
  background: white;
  font-size: 13px;
  font-weight: 500;
  color: var(--charcoal-soft);
  cursor: pointer;
  transition: var(--transition);
}

.color-pill:hover {
  border-color: var(--accent);
  background: #faf8f5;
  transform: translateY(-1px);
}

.color-pill.selected {
  border-color: var(--charcoal);
  background: var(--charcoal);
  color: white;
}

.color-dot {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  display: inline-block;
}

/* Size Pills */
.size-pills {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.size-pill {
  min-width: 50px;
  padding: 10px 14px;
  border: 1.5px solid #e5dfd6;
  border-radius: 12px;
  background: white;
  font-size: 14px;
  font-weight: 600;
  color: var(--charcoal-soft);
  cursor: pointer;
  transition: var(--transition);
  text-align: center;
}

.size-pill:hover {
  border-color: var(--accent);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.size-pill.selected {
  border-color: var(--charcoal);
  background: var(--charcoal);
  color: white;
}

/* Stock Status */
.stock-status {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: #f0f9f0;
  border-radius: 40px;
  font-size: 13px;
  font-weight: 500;
  color: #2e7d32;
  margin-bottom: 24px;
}

.stock-status.low {
  background: #fff3e0;
  color: #e65100;
}

.stock-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: currentColor;
  animation: pulse 1.5s infinite;
}

/* Product Actions */
.product-actions {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-top: 20px;
}

.quantity-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  background: #f8f8f8;
  padding: 8px;
  border-radius: 50px;
}

.quantity-btn {
  width: 40px;
  height: 40px;
  border: none;
  background: white;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: var(--transition);
  color: var(--charcoal);
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.quantity-btn:hover:not(:disabled) {
  background: var(--charcoal);
  color: white;
  transform: scale(1.05);
}

.quantity-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.quantity-value {
  font-size: 18px;
  font-weight: 600;
  min-width: 40px;
  text-align: center;
}

.add-to-cart-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  width: 100%;
  padding: 16px 24px;
  background: linear-gradient(135deg, var(--charcoal), #2d2d2d);
  color: white;
  border: none;
  border-radius: 50px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: var(--transition);
  box-shadow: 0 8px 20px rgba(0,0,0,0.2);
}

.add-to-cart-btn:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 12px 28px rgba(0,0,0,0.3);
  background: var(--charcoal-soft);
}

.add-to-cart-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  background: #9ca3af;
  transform: none;
  box-shadow: none;
}

.cart-total {
  background: rgba(255,255,255,0.2);
  padding: 4px 12px;
  border-radius: 30px;
  font-size: 14px;
  font-weight: 500;
}

/* Cart Drawer - Responsive */
.cart-drawer {
  background: white;
  width: 100%;
  max-width: 440px;
  height: 100vh;
  display: flex;
  flex-direction: column;
  animation: slideRight 0.3s cubic-bezier(.22,1,.36,1);
}

@keyframes slideRight {
  from { transform: translateX(100%); }
  to { transform: translateX(0); }
}

.cart-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 24px;
  border-bottom: 1px solid #ede8e2;
}

.cart-title {
  font-family: 'Playfair Display', serif;
  font-size: 1.3rem;
  font-weight: 700;
  margin: 0;
}

.cart-close-btn {
  background: none;
  border: none;
  cursor: pointer;
  color: var(--charcoal);
  padding: 4px;
}

.cart-items {
  flex: 1;
  overflow-y: auto;
  padding: 20px 24px;
}

.cart-empty {
  text-align: center;
  padding: 48px 0;
  color: var(--text-muted);
}

.cart-empty svg {
  margin-bottom: 12px;
  opacity: 0.4;
}

.cart-item {
  display: flex;
  gap: 14px;
  padding: 16px 0;
  border-bottom: 1px solid #f0ebe5;
}

.cart-item-image {
  width: 64px;
  height: 64px;
  border-radius: 8px;
  background: var(--cream-dark);
  overflow: hidden;
  flex-shrink: 0;
}

.cart-item-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.cart-item-image div {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #c5bfb5;
}

.cart-item-details {
  flex: 1;
  min-width: 0;
}

.cart-item-name {
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--charcoal);
  margin: 0 0 3px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.cart-item-options {
  font-size: 0.7rem;
  color: var(--text-muted);
  margin: 0 0 8px;
}

.cart-item-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.cart-item-quantity {
  display: flex;
  align-items: center;
  gap: 8px;
}

.qty-btn-small {
  width: 26px;
  height: 26px;
  border: 1px solid #d5cfc8;
  background: white;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: var(--transition);
}

.qty-btn-small:hover {
  border-color: var(--charcoal);
  background: var(--cream-dark);
}

.cart-item-price {
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--charcoal);
}

.cart-item-remove {
  background: none;
  border: none;
  cursor: pointer;
  color: var(--text-muted);
  padding: 4px;
  align-self: flex-start;
}

.cart-footer {
  padding: 20px 24px;
  border-top: 1px solid #ede8e2;
  background: var(--cream);
}

.cart-total-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  font-size: 0.85rem;
  color: var(--text-muted);
}

.cart-total-amount {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--charcoal);
}

.cart-checkout-btn {
  width: 100%;
  justify-content: center;
}

/* Checkout Modal - Responsive */
.checkout-modal {
  background: white;
  border-radius: 16px;
  max-width: 580px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
  position: relative;
  animation: slideUp 0.28s cubic-bezier(.22,1,.36,1);
}

.checkout-close-btn {
  position: absolute;
  top: 16px;
  right: 16px;
  z-index: 2;
  background: none;
  border: none;
  cursor: pointer;
  color: var(--charcoal);
  padding: 4px;
}

.checkout-content {
  padding: 32px;
}

.checkout-title {
  font-family: 'Playfair Display', serif;
  font-size: 1.5rem;
  font-weight: 700;
  margin: 0 0 4px;
  color: var(--charcoal);
}

.checkout-subtitle {
  font-size: 0.78rem;
  color: var(--text-muted);
  margin: 0 0 24px;
}

.order-summary {
  background: var(--cream);
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 24px;
}

.order-item {
  display: flex;
  justify-content: space-between;
  font-size: 0.78rem;
  color: var(--charcoal-soft);
  padding: 4px 0;
}

.order-item-options {
  color: var(--text-muted);
}

.order-item-price {
  font-weight: 600;
}

.order-total {
  border-top: 1px solid #d5cfc8;
  margin-top: 10px;
  padding-top: 10px;
  display: flex;
  justify-content: space-between;
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--charcoal);
}

.checkout-form {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.form-group.full-width {
  grid-column: span 2;
}

.form-label {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 0.75rem;
  font-weight: 500;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--text-muted);
}

.form-input {
  width: 100%;
  padding: 12px 14px;
  border: 1px solid #d5cfc8;
  border-radius: 6px;
  font-family: 'Inter', sans-serif;
  font-size: 0.85rem;
  background: white;
  color: var(--charcoal);
  outline: none;
  transition: var(--transition);
}

.form-input:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(200,169,110,0.18);
}

.form-input.error {
  border-color: #d63031;
}

.form-error {
  font-size: 0.7rem;
  color: #d63031;
  margin-top: 2px;
}

.payment-note {
  font-size: 0.72rem;
  color: var(--text-muted);
  margin: 20px 0;
  line-height: 1.5;
}

.payment-note strong {
  color: var(--charcoal);
}

.checkout-submit {
  width: 100%;
  justify-content: center;
}

/* Success Modal - Responsive */
.success-modal {
  background: white;
  border-radius: 16px;
  max-width: 420px;
  width: 90%;
  padding: 44px 32px;
  text-align: center;
  animation: slideUp 0.28s cubic-bezier(.22,1,.36,1);
}

.success-icon {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 20px;
}

.success-icon.pending {
  background: #fff8e6;
  color: #f0a500;
}

.success-icon.success {
  background: #edfbf0;
  color: #2ecc71;
}

.success-icon.failed {
  background: #fdeaea;
  color: #d63031;
}

.success-title {
  font-family: 'Playfair Display', serif;
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--charcoal);
  margin: 0 0 8px;
}

.success-message {
  font-size: 0.83rem;
  color: var(--text-muted);
  line-height: 1.6;
  margin: 0 0 20px;
}

.success-amount {
  background: var(--cream);
  border-radius: 8px;
  padding: 14px 20px;
  margin-bottom: 16px;
}

.amount-label {
  font-size: 0.7rem;
  color: var(--text-muted);
  margin: 0 0 2px;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.amount-value {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--charcoal);
  margin: 0;
}

.success-receipt {
  background: var(--cream);
  border-radius: 8px;
  padding: 14px 20px;
  margin-bottom: 16px;
}

.receipt-label {
  font-size: 0.7rem;
  color: var(--text-muted);
  margin: 0 0 2px;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.receipt-value {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--charcoal);
  margin: 0;
}

.success-reference {
  background: var(--cream);
  border-radius: 8px;
  padding: 14px 20px;
  margin-bottom: 24px;
}

.reference-label {
  font-size: 0.7rem;
  color: var(--text-muted);
  margin: 0 0 2px;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.reference-value {
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--charcoal);
  margin: 0;
  word-break: break-all;
}

.reference-help {
  font-size: 0.72rem;
  color: var(--text-muted);
  margin: 8px 0 0;
}

.status-checking {
  font-size: 0.72rem;
  color: var(--text-muted);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
}

.success-btn {
  width: 100%;
  justify-content: center;
}

/* Utility Classes */
.btn-primary {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: var(--charcoal);
  color: var(--cream);
  font-family: 'Inter', sans-serif;
  font-size: 0.82rem;
  font-weight: 500;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  padding: 14px 28px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  transition: background 0.2s, transform 0.1s;
}

.btn-primary:hover:not(:disabled) {
  background: var(--charcoal-soft);
}

.btn-primary:active:not(:disabled) {
  transform: scale(0.97);
}

.btn-primary:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.btn-outline {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: transparent;
  color: var(--charcoal);
  font-family: 'Inter', sans-serif;
  font-size: 0.82rem;
  font-weight: 500;
  letter-spacing: 0.06em;
  padding: 12px 24px;
  border: 1px solid var(--charcoal);
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-outline:hover {
  background: var(--charcoal);
  color: var(--cream);
}

.animate-spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* Responsive Breakpoints */
@media (max-width: 768px) {
  .product-modal-content {
    grid-template-columns: 1fr;
    overflow-y: auto;
  }

  .gallery-section {
    padding: 16px;
  }

  .details-section {
    padding: 24px;
  }

  .gallery-thumbnail {
    width: 60px;
    height: 60px;
  }

  .checkout-form {
    grid-template-columns: 1fr;
  }

  .form-group.full-width {
    grid-column: span 1;
  }

  .product-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }

  .hero-section {
    height: 400px;
  }

  .hero-content {
    align-items: flex-end;
    padding-bottom: 40px;
  }

  .cart-drawer {
    max-width: 100%;
  }
}

@media (max-width: 480px) {
  .nav-container {
    padding: 0 16px;
  }

  .logo {
    font-size: 1.2rem;
  }

  .hero-section {
    height: 350px;
    margin-top: 56px;
  }

  .hero-title {
    font-size: 2rem;
  }

  .hero-description {
    font-size: 0.85rem;
  }

  .product-grid {
    grid-template-columns: 1fr;
    gap: 16px;
  }

  .product-modal {
    width: 100%;
    max-width: 100%;
    max-height: 100vh;
    border-radius: 0;
  }

  .gallery-section {
    padding: 12px;
  }

  .details-section {
    padding: 20px;
  }

  .checkout-modal {
    width: 100%;
    max-width: 100%;
    max-height: 100vh;
    border-radius: 0;
  }

  .checkout-content {
    padding: 24px 16px;
  }

  .success-modal {
    width: 90%;
    padding: 32px 20px;
  }

  .whatsapp-fab {
    bottom: 16px;
    right: 16px;
    width: 48px;
    height: 48px;
  }

  .whatsapp-fab svg {
    width: 24px;
    height: 24px;
  }
}

/* Handle very small screens */
@media (max-width: 360px) {
  .color-pill {
    padding: 6px 12px;
    font-size: 12px;
  }

  .size-pill {
    min-width: 44px;
    padding: 8px 10px;
    font-size: 13px;
  }

  .quantity-btn {
    width: 36px;
    height: 36px;
  }

  .quantity-value {
    font-size: 16px;
    min-width: 32px;
  }

  .add-to-cart-btn {
    padding: 14px 20px;
    font-size: 14px;
  }
}
</style>