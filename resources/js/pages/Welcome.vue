<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { ref, computed, onMounted, watch } from 'vue'
import { ShoppingCart, X, Plus, Minus, ArrowRight, Phone, MapPin, User, Truck, Loader2 } from 'lucide-vue-next'

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

const openProductDetail = (product: any) => {
  selectedProduct.value = product
  selectedSize.value = product.sizes?.[0] || ''
  selectedColor.value = product.colors?.[0] || ''
  detailQuantity.value = 1
}

const closeProductDetail = () => {
  selectedProduct.value = null
}

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
}

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

// show success modal if flash present
onMounted(() => {
  if (orderSuccess.value) {
    isSuccessOpen.value = true
  }
})
</script>

<template>
  <Head title="Shop" />

  <!-- ─── PAGE ──────────────────────────────────────────────────────────── -->
  <div style="min-height: 100vh; background: var(--cream);">

    <!-- NAV -->
    <nav style="position: sticky; top: 0; z-index: 40; background: rgba(245,240,235,0.92); backdrop-filter: blur(8px); border-bottom: 1px solid #e0d9d0;">
      <div style="max-width: 1200px; margin: 0 auto; padding: 0 24px; height: 64px; display: flex; align-items: center; justify-content: space-between;">
        <h1 class="font-display" style="font-size: 1.5rem; font-weight: 700; letter-spacing: -0.02em; color: var(--charcoal);">
          <span style="color: var(--accent);">D</span>rmorch
        </h1>
        <button @click="isCartOpen = true" style="position: relative; background: none; border: none; cursor: pointer; padding: 8px; color: var(--charcoal);">
          <ShoppingCart :size="22" />
          <span v-if="cartCount > 0" style="position: absolute; top: 0; right: 0; background: var(--charcoal); color: white; font-size: 0.6rem; font-weight: 700; width: 17px; height: 17px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid var(--cream);">
            {{ cartCount > 9 ? '9+' : cartCount }}
          </span>
        </button>
      </div>
    </nav>

    <!-- HERO -->
    <section style="position: relative; overflow: hidden; height: 420px; display: flex; align-items: center; background: url('/images/auth-bg.jpg') center/cover;">
      <div style="position: absolute; inset: 0; background: rgba(26,26,26,0.6);"></div>
      <div style="position: relative; z-index: 1; max-width: 1200px; margin: 0 auto; padding: 0 24px; width: 100%;">
        <p style="font-size: 0.7rem; font-weight: 500; letter-spacing: 0.2em; text-transform: uppercase; color: var(--accent); margin-bottom: 16px;">New Collection 2026</p>
        <h2 class="font-display" style="font-size: clamp(2.2rem, 5vw, 3.6rem); font-weight: 700; color: white; line-height: 1.15; max-width: 560px;">
          Walk with<br><span style="color: var(--accent);">confidence.</span>
        </h2>
        <p style="margin-top: 18px; color: rgba(255,255,255,0.7); font-size: 0.9rem; max-width: 420px; line-height: 1.6;">
          Premium footwear crafted for those who value style, comfort and every step they take.
        </p>
        <button class="btn-primary" style="margin-top: 28px;" @click="document.getElementById('products-section')?.scrollIntoView({ behavior: 'smooth' })">
          Shop Now <ArrowRight :size="16" />
        </button>
      </div>
      <div style="position: absolute; bottom: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, var(--accent), transparent 60%);"></div>
    </section>

    <!-- CATEGORY FILTER -->
    <section style="max-width: 1200px; margin: 0 auto; padding: 32px 24px 0;">
      <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
        <span
            class="tag-pill"
            :class="{ active: activeCategory === 'all' }"
            @click="activeCategory = 'all'"
        >All</span>
        <span
            v-for="cat in categories"
            :key="cat.id"
            class="tag-pill"
            :class="{ active: activeCategory === cat.id }"
            @click="activeCategory = cat.id"
        >
          {{ cat.name }}
          <span v-if="cat.products_count" style="opacity:0.5; margin-left: 4px;">({{ cat.products_count }})</span>
        </span>
      </div>
    </section>

    <!-- PRODUCT GRID -->
    <section id="products-section" style="max-width: 1200px; margin: 0 auto; padding: 32px 24px 80px;">
      <div v-if="filteredProducts.length === 0" style="text-align: center; padding: 60px 0; color: var(--text-muted);">
        No products in this category yet.
      </div>
      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 24px;">
        <article
            v-for="product in filteredProducts"
            :key="product.id"
            @click="openProductDetail(product)"
            style="background: white; border-radius: 10px; overflow: hidden; cursor: pointer; border: 1px solid #ede8e2; transition: transform 0.2s, box-shadow 0.2s;"
            onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='0 8px 30px rgba(0,0,0,0.08)'"
            onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='none'"
        >
          <div style="aspect-ratio: 4/3; background: var(--cream-dark); position: relative; overflow: hidden;">
            <img
                v-if="getProductImage(product)"
                :src="getProductImage(product)"
                :alt="product.name"
                style="width: 100%; height: 100%; object-fit: cover;"
            />
            <div v-else style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
              <ShoppingCart :size="32" style="color: #c5bfb5;" />
            </div>
            <span style="position: absolute; top: 12px; left: 12px; background: rgba(255,255,255,0.9); backdrop-filter: blur(4px); padding: 4px 10px; border-radius: 999px; font-size: 0.68rem; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: var(--charcoal-soft);">
              {{ product.category?.name }}
            </span>
          </div>
          <div style="padding: 16px;">
            <h3 style="font-size: 0.92rem; font-weight: 600; color: var(--charcoal); margin: 0 0 6px;">{{ product.name }}</h3>
            <div style="display: flex; align-items: center; justify-content: space-between;">
              <span style="font-size: 1rem; font-weight: 700; color: var(--charcoal);">{{ formatPrice(product.price) }}</span>
              <span style="font-size: 0.7rem; color: var(--text-muted);">
                {{ (product.colors || []).length }} colors · {{ (product.sizes || []).length }} sizes
              </span>
            </div>
          </div>
        </article>
      </div>
    </section>

    <!-- FOOTER -->
    <footer style="background: var(--charcoal); color: rgba(255,255,255,0.5); padding: 40px 24px; text-align: center;">
      <p class="font-display" style="font-size: 1.1rem; color: white; margin: 0 0 8px;"><span style="color: var(--accent);">D</span>rmorch</p>
      <p style="font-size: 0.78rem; margin: 0;">© 2026 Drmorch. All rights reserved.</p>
    </footer>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <!-- PRODUCT DETAIL MODAL                                                    -->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <div v-if="selectedProduct" class="modal-overlay" @click.self="closeProductDetail">
    <div class="modal-content" style="background: white; border-radius: 12px; max-width: 700px; width: 90vw; max-height: 88vh; overflow-y: auto; position: relative;">
      <button @click="closeProductDetail" style="position: absolute; top: 16px; right: 16px; z-index: 2; background: rgba(255,255,255,0.9); border: none; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <X :size="18" style="color: var(--charcoal);" />
      </button>

      <div style="aspect-ratio: 4/3; max-height: 280px; background: var(--cream-dark); overflow: hidden;">
        <img
            v-if="getProductImage(selectedProduct)"
            :src="getProductImage(selectedProduct)"
            :alt="selectedProduct.name"
            style="width: 100%; height: 100%; object-fit: cover;"
        />
        <div v-else style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
          <ShoppingCart :size="48" style="color: #c5bfb5;" />
        </div>
      </div>

      <div style="padding: 28px;">
        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;">
          <div>
            <span style="font-size: 0.68rem; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: var(--accent);">{{ selectedProduct.category?.name }}</span>
            <h2 class="font-display" style="font-size: 1.6rem; font-weight: 700; margin: 4px 0 0; color: var(--charcoal);">{{ selectedProduct.name }}</h2>
          </div>
          <span style="font-size: 1.4rem; font-weight: 700; color: var(--charcoal); white-space: nowrap;">{{ formatPrice(selectedProduct.price) }}</span>
        </div>

        <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.7; margin: 16px 0 24px;">{{ selectedProduct.description }}</p>

        <div v-if="selectedProduct.colors && selectedProduct.colors.length" style="margin-bottom: 20px;">
          <label class="shop-label">Color</label>
          <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <button
                v-for="color in selectedProduct.colors"
                :key="color"
                class="option-btn"
                :class="{ selected: selectedColor === color }"
                @click="selectedColor = color"
            >{{ color }}</button>
          </div>
        </div>

        <div v-if="selectedProduct.sizes && selectedProduct.sizes.length" style="margin-bottom: 20px;">
          <label class="shop-label">Size</label>
          <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <button
                v-for="size in selectedProduct.sizes"
                :key="size"
                class="option-btn"
                :class="{ selected: selectedSize === size }"
                @click="selectedSize = size"
            >{{ size }}</button>
          </div>
        </div>

        <div style="margin-bottom: 28px;">
          <label class="shop-label">Quantity</label>
          <div style="display: flex; align-items: center; gap: 12px;">
            <button class="qty-btn" @click="detailQuantity = Math.max(1, detailQuantity - 1)">
              <Minus :size="14" style="color: var(--charcoal);" />
            </button>
            <span style="font-size: 0.95rem; font-weight: 600; min-width: 20px; text-align: center;">{{ detailQuantity }}</span>
            <button class="qty-btn" @click="detailQuantity = Math.min(selectedProduct.stock, detailQuantity + 1)">
              <Plus :size="14" style="color: var(--charcoal);" />
            </button>
            <span style="font-size: 0.72rem; color: var(--text-muted); margin-left: 8px;">{{ selectedProduct.stock }} in stock</span>
          </div>
        </div>

        <button
            class="btn-primary"
            style="width: 100%; justify-content: center;"
            :disabled="!selectedSize || !selectedColor"
            @click="addToCart"
        >
          Add to Cart — {{ formatPrice(selectedProduct.price * detailQuantity) }}
        </button>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <!-- CART DRAWER                                                             -->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <div v-if="isCartOpen" class="modal-overlay side-right" @click.self="isCartOpen = false">
    <div class="modal-side" style="background: white; width: 100%; max-width: 440px; height: 100vh; display: flex; flex-direction: column;">
      <div style="display: flex; align-items: center; justify-content: space-between; padding: 24px; border-bottom: 1px solid #ede8e2;">
        <h2 class="font-display" style="font-size: 1.3rem; font-weight: 700; margin: 0;">Your Cart</h2>
        <button @click="isCartOpen = false" style="background: none; border: none; cursor: pointer; color: var(--charcoal);">
          <X :size="20" />
        </button>
      </div>

      <div style="flex: 1; overflow-y: auto; padding: 20px 24px;">
        <div v-if="cart.length === 0" style="text-align: center; padding: 48px 0; color: var(--text-muted);">
          <ShoppingCart :size="36" style="margin-bottom: 12px; opacity: 0.4;" />
          <p style="font-size: 0.85rem; margin: 0;">Your cart is empty</p>
        </div>
        <div v-for="(item, index) in cart" :key="index" style="display: flex; gap: 14px; padding: 16px 0; border-bottom: 1px solid #f0ebe5;">
          <div style="width: 64px; height: 64px; border-radius: 8px; background: var(--cream-dark); overflow: hidden; flex-shrink: 0;">
            <img v-if="getProductImage(item.product)" :src="getProductImage(item.product)" style="width: 100%; height: 100%; object-fit: cover;" />
            <div v-else style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;"><ShoppingCart :size="18" style="color: #c5bfb5;"/></div>
          </div>
          <div style="flex: 1; min-width: 0;">
            <p style="font-size: 0.82rem; font-weight: 600; color: var(--charcoal); margin: 0 0 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ item.product.name }}</p>
            <p style="font-size: 0.7rem; color: var(--text-muted); margin: 0 0 8px;">{{ item.color }} · Size {{ item.size }}</p>
            <div style="display: flex; align-items: center; justify-content: space-between;">
              <div style="display: flex; align-items: center; gap: 8px;">
                <button class="qty-btn" style="width: 26px; height: 26px;" @click="updateQuantity(index, -1)">
                  <Minus :size="12" style="color: var(--charcoal);" />
                </button>
                <span style="font-size: 0.8rem; font-weight: 600;">{{ item.quantity }}</span>
                <button class="qty-btn" style="width: 26px; height: 26px;" @click="updateQuantity(index, 1)">
                  <Plus :size="12" style="color: var(--charcoal);" />
                </button>
              </div>
              <span style="font-size: 0.82rem; font-weight: 600; color: var(--charcoal);">{{ formatPrice(item.product.price * item.quantity) }}</span>
            </div>
          </div>
          <button @click="removeFromCart(index)" style="background: none; border: none; cursor: pointer; color: var(--text-muted); padding: 4px;" title="Remove">
            <X :size="16" />
          </button>
        </div>
      </div>

      <div v-if="cart.length > 0" style="padding: 20px 24px; border-top: 1px solid #ede8e2; background: var(--cream);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
          <span style="font-size: 0.85rem; color: var(--text-muted);">Total</span>
          <span style="font-size: 1.1rem; font-weight: 700; color: var(--charcoal);">{{ formatPrice(cartTotal) }}</span>
        </div>
        <button class="btn-primary" style="width: 100%; justify-content: center;" @click="openCheckout">
          Proceed to Checkout <ArrowRight :size="16" />
        </button>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <!-- CHECKOUT MODAL                                                          -->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <div v-if="isCheckoutOpen" class="modal-overlay" @click.self="isCheckoutOpen = false">
    <div class="modal-content" style="background: white; border-radius: 12px; max-width: 580px; width: 90vw; max-height: 90vh; overflow-y: auto; position: relative;">
      <button @click="isCheckoutOpen = false" style="position: absolute; top: 16px; right: 16px; z-index: 2; background: none; border: none; cursor: pointer; color: var(--charcoal);">
        <X :size="20" />
      </button>

      <div style="padding: 32px;">
        <h2 class="font-display" style="font-size: 1.5rem; font-weight: 700; margin: 0 0 4px; color: var(--charcoal);">Checkout</h2>
        <p style="font-size: 0.78rem; color: var(--text-muted); margin: 0 0 24px;">Fill in your details and we'll send an M-Pesa prompt to your phone.</p>

        <div style="background: var(--cream); border-radius: 8px; padding: 16px; margin-bottom: 24px;">
          <div v-for="(item, i) in cart" :key="i" style="display: flex; justify-content: space-between; font-size: 0.78rem; color: var(--charcoal-soft); padding: 4px 0;">
            <span>{{ item.product.name }} <span style="color: var(--text-muted);">({{ item.color }}, {{ item.size }}) x{{ item.quantity }}</span></span>
            <span style="font-weight: 600;">{{ formatPrice(item.product.price * item.quantity) }}</span>
          </div>
          <div style="border-top: 1px solid #d5cfc8; margin-top: 10px; padding-top: 10px; display: flex; justify-content: space-between; font-size: 0.85rem; font-weight: 700; color: var(--charcoal);">
            <span>Total</span>
            <span>{{ formatPrice(cartTotal) }}</span>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
          <div>
            <label class="shop-label"><User :size="12" style="vertical-align: middle; margin-right: 4px;" />Name</label>
            <input v-model="orderForm.customer_name" class="shop-input" :class="{ error: orderForm.errors.customer_name }" placeholder="e.g. John Doe" />
            <span v-if="orderForm.errors.customer_name" style="font-size: 0.7rem; color: #d63031; margin-top: 4px;">{{ orderForm.errors.customer_name }}</span>
          </div>
          <div>
            <label class="shop-label"><Phone :size="12" style="vertical-align: middle; margin-right: 4px;" />M-Pesa No</label>
            <input v-model="orderForm.mpesa_number" class="shop-input" :class="{ error: orderForm.errors.mpesa_number }" placeholder="e.g. 0712345678" type="tel" />
            <span v-if="orderForm.errors.mpesa_number" style="font-size: 0.7rem; color: #d63031; margin-top: 4px;">{{ orderForm.errors.mpesa_number }}</span>
          </div>
          <div style="grid-column: span 2;">
            <label class="shop-label"><MapPin :size="12" style="vertical-align: middle; margin-right: 4px;" />Town</label>
            <input v-model="orderForm.town" class="shop-input" :class="{ error: orderForm.errors.town }" placeholder="e.g. Nairobi" />
            <span v-if="orderForm.errors.town" style="font-size: 0.7rem; color: #d63031; margin-top: 4px;">{{ orderForm.errors.town }}</span>
          </div>
          <div style="grid-column: span 2;">
            <label class="shop-label"><Truck :size="12" style="vertical-align: middle; margin-right: 4px;" />Delivery / Pickup Details</label>
            <textarea v-model="orderForm.description" class="shop-input" :class="{ error: orderForm.errors.description }" placeholder="Describe your delivery address or pickup point..." rows="2" style="resize: vertical;"></textarea>
            <span v-if="orderForm.errors.description" style="font-size: 0.7rem; color: #d63031; margin-top: 4px;">{{ orderForm.errors.description }}</span>
          </div>
        </div>

        <p style="font-size: 0.72rem; color: var(--text-muted); margin: 20px 0; line-height: 1.5;">
          An M-Pesa payment request of <strong style="color: var(--charcoal);">{{ formatPrice(cartTotal) }}</strong> will be sent to your phone immediately after placing the order.
        </p>

        <button
            class="btn-primary"
            style="width: 100%; justify-content: center;"
            :disabled="orderForm.processing"
            @click="submitOrder"
        >
          <Loader2 v-if="orderForm.processing" :size="16" class="animate-spin" />
          {{ orderForm.processing ? 'Placing Order...' : 'Place Order & Pay' }} <ArrowRight v-if="!orderForm.processing" :size="16" />
        </button>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <!-- ORDER SUCCESS / PAYMENT STATUS MODAL                                    -->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <div v-if="isSuccessOpen" class="modal-overlay">
    <div class="modal-content" style="background: white; border-radius: 12px; max-width: 420px; width: 90vw; padding: 44px 32px; text-align: center;">

      <!-- Pending Payment -->
      <template v-if="paymentStatus === 'pending'">
        <div style="width: 72px; height: 72px; border-radius: 50%; background: #fff8e6; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
          <Loader2 :size="32" style="color: #f0a500;" class="animate-spin" />
        </div>
        <h2 class="font-display" style="font-size: 1.5rem; font-weight: 700; color: var(--charcoal); margin: 0 0 8px;">Waiting for Payment</h2>
        <p style="font-size: 0.83rem; color: var(--text-muted); line-height: 1.6; margin: 0 0 20px;">
          {{ orderSuccess?.stk_message || 'Check your phone for the M-Pesa prompt and enter your PIN to complete payment.' }}
        </p>
        <div style="background: var(--cream); border-radius: 8px; padding: 14px 20px; margin-bottom: 16px;">
          <p style="font-size: 0.7rem; color: var(--text-muted); margin: 0 0 2px; text-transform: uppercase; letter-spacing: 0.06em;">Amount</p>
          <p style="font-size: 1.1rem; font-weight: 700; color: var(--charcoal); margin: 0;">{{ formatPrice(orderSuccess?.amount || 0) }}</p>
        </div>
        <p style="font-size: 0.72rem; color: var(--text-muted); margin: 0;">
          <Loader2 :size="12" class="animate-spin" style="display: inline; vertical-align: middle; margin-right: 4px;" />
          Checking payment status...
        </p>
      </template>

      <!-- Payment Successful -->
      <template v-else-if="paymentStatus === 'completed'">
        <div style="width: 72px; height: 72px; border-radius: 50%; background: #edfbf0; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#2ecc71" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
        </div>
        <h2 class="font-display" style="font-size: 1.5rem; font-weight: 700; color: var(--charcoal); margin: 0 0 8px;">Payment Successful!</h2>
        <p style="font-size: 0.83rem; color: var(--text-muted); line-height: 1.6; margin: 0 0 20px;">
          Thank you for your purchase. Your order is being processed.
        </p>
        <div style="background: var(--cream); border-radius: 8px; padding: 14px 20px; margin-bottom: 16px;">
          <p style="font-size: 0.7rem; color: var(--text-muted); margin: 0 0 2px; text-transform: uppercase; letter-spacing: 0.06em;">M-Pesa Receipt</p>
          <p style="font-size: 0.9rem; font-weight: 600; color: var(--charcoal); margin: 0;">{{ mpesaReceipt }}</p>
        </div>
        <div v-if="orderSuccess" style="background: var(--cream); border-radius: 8px; padding: 14px 20px; margin-bottom: 24px;">
          <p style="font-size: 0.7rem; color: var(--text-muted); margin: 0 0 2px; text-transform: uppercase; letter-spacing: 0.06em;">Order Reference</p>
          <p style="font-size: 0.82rem; font-weight: 600; color: var(--charcoal); margin: 0; word-break: break-all;">{{ orderSuccess.uuid }}</p>
        </div>
        <button class="btn-primary" style="width: 100%; justify-content: center;" @click="closeSuccessModal">
          Continue Shopping
        </button>
      </template>

      <!-- Payment Failed -->
      <template v-else-if="paymentStatus === 'failed'">
        <div style="width: 72px; height: 72px; border-radius: 50%; background: #fdeaea; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
          <X :size="32" style="color: #d63031;" />
        </div>
        <h2 class="font-display" style="font-size: 1.5rem; font-weight: 700; color: var(--charcoal); margin: 0 0 8px;">Payment Failed</h2>
        <p style="font-size: 0.83rem; color: var(--text-muted); line-height: 1.6; margin: 0 0 20px;">
          The payment was not completed. This could be due to insufficient funds, wrong PIN, or cancelled request.
        </p>
        <div v-if="orderSuccess" style="background: var(--cream); border-radius: 8px; padding: 14px 20px; margin-bottom: 24px;">
          <p style="font-size: 0.7rem; color: var(--text-muted); margin: 0 0 2px; text-transform: uppercase; letter-spacing: 0.06em;">Order Reference</p>
          <p style="font-size: 0.82rem; font-weight: 600; color: var(--charcoal); margin: 0; word-break: break-all;">{{ orderSuccess.uuid }}</p>
          <p style="font-size: 0.72rem; color: var(--text-muted); margin: 8px 0 0;">Contact us with this reference to retry payment.</p>
        </div>
        <button class="btn-outline" style="width: 100%; justify-content: center;" @click="closeSuccessModal">
          Close
        </button>
      </template>

      <!-- Initial Success (STK not sent or no polling) -->
      <template v-else>
        <div style="width: 72px; height: 72px; border-radius: 50%; background: #edfbf0; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#2ecc71" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
        </div>
        <h2 class="font-display" style="font-size: 1.5rem; font-weight: 700; color: var(--charcoal); margin: 0 0 8px;">Order Placed!</h2>
        <p style="font-size: 0.83rem; color: var(--text-muted); line-height: 1.6; margin: 0 0 20px;">
          {{ orderSuccess?.stk_sent ? orderSuccess.stk_message : 'Your order has been received. We will contact you shortly regarding payment.' }}
        </p>
        <div v-if="orderSuccess" style="background: var(--cream); border-radius: 8px; padding: 14px 20px; margin-bottom: 24px;">
          <p style="font-size: 0.7rem; color: var(--text-muted); margin: 0 0 2px; text-transform: uppercase; letter-spacing: 0.06em;">Order Reference</p>
          <p style="font-size: 0.82rem; font-weight: 600; color: var(--charcoal); margin: 0; word-break: break-all;">{{ orderSuccess.uuid }}</p>
        </div>
        <button class="btn-outline" style="width: 100%; justify-content: center;" @click="closeSuccessModal">
          Continue Shopping
        </button>
      </template>
    </div>
  </div>
</template>

<!-- ─── STYLES ────────────────────────────────────────────────────────── -->
<style>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Inter:wght@300;400;500;600&display=swap');

:root {
  --cream: #f5f0eb;
  --cream-dark: #ebe5dc;
  --charcoal: #1a1a1a;
  --charcoal-soft: #3a3a3a;
  --accent: #c8a96e;
  --accent-light: #dcc49a;
  --accent-dark: #a07b3e;
  --text-muted: #7a7168;
}

* { box-sizing: border-box; }

body {
  font-family: 'Inter', sans-serif;
  background: var(--cream);
  color: var(--charcoal);
  -webkit-font-smoothing: antialiased;
}

.font-display { font-family: 'Playfair Display', serif; }

::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--accent-light); border-radius: 3px; }

.modal-overlay {
  position: fixed; inset: 0; z-index: 50;
  background: rgba(26,26,26,0.55);
  backdrop-filter: blur(2px);
  display: flex; align-items: center; justify-content: center;
  animation: fadeIn 0.2s ease;
}
.modal-overlay.side-right {
  align-items: stretch; justify-content: flex-end;
}

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { transform: translateY(24px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
@keyframes slideRight { from { transform: translateX(100%); } to { transform: translateX(0); } }

.modal-content { animation: slideUp 0.28s cubic-bezier(.22,1,.36,1); }
.modal-side { animation: slideRight 0.3s cubic-bezier(.22,1,.36,1); }

.tag-pill {
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
  transition: all 0.2s;
}
.tag-pill:hover { border-color: var(--accent); }
.tag-pill.active {
  background: var(--charcoal);
  color: var(--cream);
  border-color: var(--charcoal);
}

.btn-primary {
  display: inline-flex; align-items: center; justify-content: center; gap: 8px;
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
.btn-primary:hover { background: var(--charcoal-soft); }
.btn-primary:active { transform: scale(0.97); }
.btn-primary:disabled { opacity: 0.45; cursor: not-allowed; }

.btn-outline {
  display: inline-flex; align-items: center; justify-content: center; gap: 8px;
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
.btn-outline:hover { background: var(--charcoal); color: var(--cream); }

.shop-input {
  width: 100%;
  padding: 12px 14px;
  border: 1px solid #d5cfc8;
  border-radius: 6px;
  font-family: 'Inter', sans-serif;
  font-size: 0.85rem;
  background: white;
  color: var(--charcoal);
  outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.shop-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(200,169,110,0.18); }
.shop-input::placeholder { color: var(--text-muted); }
.shop-input.error { border-color: #d63031; }

.shop-label {
  display: block;
  font-size: 0.75rem;
  font-weight: 500;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--text-muted);
  margin-bottom: 6px;
}

.option-btn {
  padding: 8px 14px;
  border: 1px solid #d5cfc8;
  border-radius: 6px;
  background: white;
  font-size: 0.8rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s;
}
.option-btn:hover { border-color: var(--accent); }
.option-btn.selected {
  background: var(--charcoal);
  color: white;
  border-color: var(--charcoal);
}

.qty-btn {
  width: 32px; height: 32px;
  border: 1px solid #d5cfc8;
  background: white;
  border-radius: 6px;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  transition: all 0.15s;
}
.qty-btn:hover { border-color: var(--charcoal); background: var(--cream-dark); }

.animate-spin {
  animation: spin 1s linear infinite;
}
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>