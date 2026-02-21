<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import {dashboard} from '@/routes';
import {type BreadcrumbItem} from '@/types';
import {Head, router, useForm} from '@inertiajs/vue3';
import {Edit, Trash2, Plus, Upload, X} from 'lucide-vue-next';
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog'
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import {Button} from '@/components/ui/button'
import {Input} from '@/components/ui/input'
import {Label} from '@/components/ui/label'
import {Select, SelectContent, SelectItem, SelectTrigger, SelectValue} from '@/components/ui/select'
import {toast} from 'vue-sonner'
import {ref, computed} from 'vue'
import Pagination from '@/components/Pagination.vue'

const props = defineProps<{
  products?: { data: any[]; links: any[] }
  categories?: any[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  {title: 'Dashboard', href: dashboard().url},
  {title: 'Products'},
]

const productsList    = computed(() => props.products?.data || [])
const paginationLinks = computed(() => props.products?.links || [])

const availableColors = ['white', 'black', 'grey', 'dark tan', 'mid brown', 'light tan']
const availableSizes  = Array.from({length: 28}, (_, i) => (i + 20).toString())
const MAX_IMAGES      = 3

interface ImagePreview { url: string; name: string }
function buildPreviews(files: File[]): ImagePreview[] {
  return files.map((f) => ({url: URL.createObjectURL(f), name: f.name}))
}

// ─── Add Modal ──────────────────────────────────────────────────
const isAddDialogOpen = ref(false)
const addForm = useForm({
  category_id: '',
  name: '',
  description: '',
  price: '',
  stock: '',
  colors: [] as string[],
  sizes: [] as string[],
  images: [] as File[],
})

const addImagePreviews   = computed(() => buildPreviews(addForm.images))
const addCanUpload       = computed(() => addForm.images.length < MAX_IMAGES)
const addRemainingSlots  = computed(() => MAX_IMAGES - addForm.images.length)
const addIsDragging      = ref(false)

const openAddDialog  = () => { addForm.reset(); addForm.clearErrors(); isAddDialogOpen.value = true }
const closeAddDialog = () => { isAddDialogOpen.value = false; addForm.reset(); addForm.clearErrors() }

const toggleAddColor = (color: string) => {
  const i = addForm.colors.indexOf(color)
  i === -1 ? addForm.colors.push(color) : addForm.colors.splice(i, 1)
}
const toggleAddSize = (size: string) => {
  const i = addForm.sizes.indexOf(size)
  i === -1 ? addForm.sizes.push(size) : addForm.sizes.splice(i, 1)
}

async function compressImage(file: File): Promise<File> {
  return new Promise((resolve) => {
    const img = new Image()
    const url = URL.createObjectURL(file)
    img.onload = () => {
      const maxWidth = 1200
      const scale = Math.min(1, maxWidth / img.width)
      const canvas = document.createElement('canvas')
      canvas.width = img.width * scale
      canvas.height = img.height * scale
      const ctx = canvas.getContext('2d')!
      ctx.drawImage(img, 0, 0, canvas.width, canvas.height)
      canvas.toBlob((blob) => {
        URL.revokeObjectURL(url)
        resolve(new File([blob!], file.name.replace(/\.[^.]+$/, '.webp'), { type: 'image/webp' }))
      }, 'image/webp', 0.8)
    }
    img.src = url
  })
}

async function addHandleFiles(files: FileList | File[]) {
  const raw = Array.from(files).slice(0, addRemainingSlots.value).filter((f) => f.type.startsWith('image/'))
  const compressed = await Promise.all(raw.map(compressImage))
  addForm.images = [...addForm.images, ...compressed]
}
const addOnDrop = async (e: DragEvent) => {
  e.preventDefault()
  addIsDragging.value = false
  if (e.dataTransfer?.files) await addHandleFiles(e.dataTransfer.files)
}
const addOnFileInput = async (e: Event) => {
  const input = e.target as HTMLInputElement
  if (input.files) await addHandleFiles(input.files)
  input.value = ''
}
const removeAddImage = (index: number) => {
  const updated = [...addForm.images]; updated.splice(index, 1); addForm.images = updated
}
const handleAddSubmit = () => {
  addForm.post('/products', {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Product Added', {description: `${addForm.name} has been added successfully.`})
      isAddDialogOpen.value = false
      addForm.reset()
    },
    onError: () => toast.error('Validation Error', {description: 'Please check the form for errors.'}),
  })
}

// ─── Edit Modal ─────────────────────────────────────────────────
const isEditDialogOpen = ref(false)
const editingProduct   = ref<any>(null)
const editForm = useForm({
  id: '',
  category_id: '',
  name: '',
  description: '',
  price: '',
  stock: '',
  colors: [] as string[],
  sizes: [] as string[],
  images: [] as File[],
  removed_image_ids: [] as number[],
})

const editExistingImages  = computed(() =>
    (editingProduct.value?.images || []).filter((img: any) => !editForm.removed_image_ids.includes(img.id))
)
const editTotalCount      = computed(() => editExistingImages.value.length + editForm.images.length)
const editCanUpload       = computed(() => editTotalCount.value < MAX_IMAGES)
const editRemainingSlots  = computed(() => MAX_IMAGES - editTotalCount.value)
const editIsDragging      = ref(false)
const editImagePreviews   = computed(() => buildPreviews(editForm.images))

const openEditDialog = (product: any) => {
  editingProduct.value       = product
  editForm.id                = product.id
  editForm.category_id       = product.category_id.toString()
  editForm.name              = product.name
  editForm.description       = product.description
  editForm.price             = product.price.toString()
  editForm.stock             = product.stock.toString()
  editForm.colors            = product.colors || []
  editForm.sizes             = product.sizes || []
  editForm.images            = []
  editForm.removed_image_ids = []
  editForm.clearErrors()
  isEditDialogOpen.value = true
}
const closeEditDialog = () => {
  isEditDialogOpen.value = false; editingProduct.value = null; editForm.reset(); editForm.clearErrors()
}

const toggleEditColor = (color: string) => {
  const i = editForm.colors.indexOf(color)
  i === -1 ? editForm.colors.push(color) : editForm.colors.splice(i, 1)
}
const toggleEditSize = (size: string) => {
  const i = editForm.sizes.indexOf(size)
  i === -1 ? editForm.sizes.push(size) : editForm.sizes.splice(i, 1)
}
async function editHandleFiles(files: FileList | File[]) {
  const raw = Array.from(files).slice(0, editRemainingSlots.value).filter((f) => f.type.startsWith('image/'))
  const compressed = await Promise.all(raw.map(compressImage))
  editForm.images = [...editForm.images, ...compressed]
}
const editOnDrop = async (e: DragEvent) => {
  e.preventDefault()
  editIsDragging.value = false
  if (e.dataTransfer?.files) await editHandleFiles(e.dataTransfer.files)
}
const editOnFileInput = async (e: Event) => {
  const input = e.target as HTMLInputElement
  if (input.files) await editHandleFiles(input.files)
  input.value = ''
}
const removeEditImage     = (index: number) => {
  const updated = [...editForm.images]; updated.splice(index, 1); editForm.images = updated
}
const removeExistingImage = (id: number) => {
  editForm.removed_image_ids = [...editForm.removed_image_ids, id]
}
const handleEditSubmit = () => {
  editForm.post(`/products/${editForm.id}/update`, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Product Updated', {description: `${editForm.name} has been updated successfully.`})
      isEditDialogOpen.value = false
      editingProduct.value   = null
    },
    onError: () => toast.error('Validation Error', {description: 'Please check the form for errors.'}),
  })
}

// ─── Delete Modal ───────────────────────────────────────────────
const isDeleteDialogOpen = ref(false)
const deletingProduct    = ref<any>(null)

const openDeleteDialog  = (product: any) => { deletingProduct.value = product; isDeleteDialogOpen.value = true }
const closeDeleteDialog = () => { isDeleteDialogOpen.value = false; deletingProduct.value = null }

const handleDeleteConfirm = () => {
  if (!deletingProduct.value) return
  router.delete(`/products/${deletingProduct.value.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Product Deleted', {description: `${deletingProduct.value.name} has been deleted.`})
      isDeleteDialogOpen.value = false
      deletingProduct.value    = null
    },
    onError: () => toast.error('Delete Failed', {description: 'Unable to delete product. Please try again.'}),
  })
}
</script>

<template>
  <Head title="Products"/>

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
      <div class="relative min-h-[100vh] flex-1 rounded-xl border border-gray-200 bg-white md:min-h-min dark:border-gray-700 dark:bg-gray-800">
        <div class="p-6">
          <div class="mb-4 flex items-center justify-between">
            <h2 class="text-xl font-semibold">Products</h2>
            <Button @click="openAddDialog">
              <Plus class="mr-2 h-4 w-4"/> Add Product
            </Button>
          </div>

          <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>#</TableHead>
                  <TableHead>Name</TableHead>
                  <TableHead>Category</TableHead>
                  <TableHead>Price</TableHead>
                  <TableHead>Stock</TableHead>
                  <TableHead>SKU</TableHead>
                  <TableHead class="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-if="productsList.length === 0">
                  <TableCell colspan="7" class="h-24 text-center text-gray-500 dark:text-gray-400">
                    No products found.
                  </TableCell>
                </TableRow>
                <TableRow v-for="(product, index) in productsList" :key="product.id">
                  <TableCell class="font-medium">{{ index + 1 }}</TableCell>
                  <TableCell>
                    <div class="flex items-center gap-3">
                      <div v-if="product.images && product.images.length > 0"
                           class="h-10 w-10 flex-shrink-0 overflow-hidden rounded-md border border-gray-200 dark:border-gray-700">
                        <img :src="'/' + product.images[0].path" alt="" class="h-full w-full object-cover"/>
                      </div>
                      <div v-else
                           class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-md border border-dashed border-gray-300 bg-muted/50 dark:border-gray-600">
                        <Upload class="h-4 w-4 text-muted-foreground"/>
                      </div>
                      <span>{{ product.name }}</span>
                    </div>
                  </TableCell>
                  <TableCell class="text-gray-500 dark:text-gray-400">{{ product.category?.name }}</TableCell>
                  <TableCell>KES {{ Number(product.price).toLocaleString() }}</TableCell>
                  <TableCell>
                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold"
                          :class="{
                            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': product.stock > 10,
                            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': product.stock > 0 && product.stock <= 10,
                            'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': product.stock === 0,
                          }">
                      {{ product.stock }}
                    </span>
                  </TableCell>
                  <TableCell class="text-sm text-gray-500 dark:text-gray-400">{{ product.sku }}</TableCell>
                  <TableCell class="text-right">
                    <div class="flex items-center justify-end gap-2">
                      <button @click="openEditDialog(product)"
                              class="inline-flex items-center gap-1 rounded-md bg-blue-100 px-3 py-1.5 text-sm font-medium text-blue-700 hover:bg-blue-200 dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800">
                        <Edit class="h-4 w-4"/> Edit
                      </button>
                      <button @click="openDeleteDialog(product)"
                              class="inline-flex items-center gap-1 rounded-md bg-red-100 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-200 dark:bg-red-900 dark:text-red-200 dark:hover:bg-red-800">
                        <Trash2 class="h-4 w-4"/> Delete
                      </button>
                    </div>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>

          <div class="mt-4 flex items-center justify-between">
            <div class="text-sm text-gray-500 dark:text-gray-400">
              Showing {{ productsList.length }} products on this page
            </div>
            <Pagination :links="paginationLinks"/>
          </div>
        </div>
      </div>
    </div>

    <!-- ─── Add Product Dialog ──────────────────────────────────── -->
    <Dialog v-model:open="isAddDialogOpen">
      <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-[650px]"
                     @interact-outside="(e) => e.preventDefault()">
        <DialogHeader>
          <DialogTitle>Add New Product</DialogTitle>
          <DialogDescription>Enter the product details below.</DialogDescription>
        </DialogHeader>

        <form @submit.prevent="handleAddSubmit">
          <div class="grid gap-4 py-4">
            <div class="grid grid-cols-2 gap-4">
              <div class="grid gap-2">
                <Label for="add-name">Name</Label>
                <Input id="add-name" v-model="addForm.name" placeholder="Product name" required/>
                <p v-if="addForm.errors.name" class="text-xs text-red-500">{{ addForm.errors.name }}</p>
              </div>
              <div class="grid gap-2">
                <Label for="add-category">Category</Label>
                <Select v-model="addForm.category_id" required>
                  <SelectTrigger id="add-category"><SelectValue placeholder="Select category"/></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="cat in categories" :key="cat.id" :value="cat.id.toString()">
                      {{ cat.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p v-if="addForm.errors.category_id" class="text-xs text-red-500">{{ addForm.errors.category_id }}</p>
              </div>
            </div>

            <div class="grid gap-2">
              <Label for="add-description">Description</Label>
              <textarea id="add-description" v-model="addForm.description" rows="3"
                        class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                        placeholder="Product description" required/>
              <p v-if="addForm.errors.description" class="text-xs text-red-500">{{ addForm.errors.description }}</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div class="grid gap-2">
                <Label for="add-price">Price (KES)</Label>
                <Input id="add-price" v-model="addForm.price" type="number" placeholder="0.00" min="0" required/>
                <p v-if="addForm.errors.price" class="text-xs text-red-500">{{ addForm.errors.price }}</p>
              </div>
              <div class="grid gap-2">
                <Label for="add-stock">Stock</Label>
                <Input id="add-stock" v-model="addForm.stock" type="number" placeholder="0" min="0" required/>
                <p v-if="addForm.errors.stock" class="text-xs text-red-500">{{ addForm.errors.stock }}</p>
              </div>
            </div>

            <div class="grid gap-2">
              <Label>Colors</Label>
              <div class="flex flex-wrap gap-2">
                <button v-for="color in availableColors" :key="color" type="button" @click="toggleAddColor(color)"
                        class="inline-flex rounded-full border px-3 py-1 text-xs font-medium transition-colors"
                        :class="addForm.colors.includes(color) ? 'border-primary bg-primary text-primary-foreground' : 'border-muted bg-muted text-muted-foreground hover:bg-muted/80'">
                  {{ color }}
                </button>
              </div>
              <p v-if="addForm.errors.colors" class="text-xs text-red-500">{{ addForm.errors.colors }}</p>
            </div>

            <div class="grid gap-2">
              <Label>Sizes</Label>
              <div class="flex flex-wrap gap-2">
                <button v-for="size in availableSizes" :key="size" type="button" @click="toggleAddSize(size)"
                        class="inline-flex rounded-md border px-3 py-1 text-xs font-medium transition-colors"
                        :class="addForm.sizes.includes(size) ? 'border-primary bg-primary text-primary-foreground' : 'border-muted bg-muted text-muted-foreground hover:bg-muted/80'">
                  {{ size }}
                </button>
              </div>
              <p v-if="addForm.errors.sizes" class="text-xs text-red-500">{{ addForm.errors.sizes }}</p>
            </div>

            <div class="grid gap-3">
              <div class="flex items-center justify-between">
                <Label>Product Images</Label>
                <span class="text-xs text-muted-foreground">{{ addForm.images.length }} / {{ MAX_IMAGES }}</span>
              </div>
              <div v-if="addImagePreviews.length > 0" class="grid grid-cols-3 gap-3">
                <div v-for="(preview, index) in addImagePreviews" :key="index"
                     class="group relative aspect-square overflow-hidden rounded-lg border border-blue-300 ring-2 ring-blue-100 dark:border-blue-800 dark:ring-blue-900">
                  <img :src="preview.url" alt="Preview" class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"/>
                  <div class="absolute inset-0 bg-black/0 transition-colors duration-200 group-hover:bg-black/40"/>
                  <button type="button" @click="removeAddImage(index)"
                          class="absolute right-2 top-2 rounded-full bg-red-500 p-1 text-white opacity-0 transition-opacity duration-200 hover:bg-red-600 group-hover:opacity-100">
                    <X class="h-3 w-3"/>
                  </button>
                </div>
              </div>
              <div v-if="addCanUpload"
                   @drop="addOnDrop" @dragover.prevent="addIsDragging = true" @dragleave="addIsDragging = false"
                   @click="($refs.addFileInput as HTMLInputElement).click()"
                   class="relative cursor-pointer rounded-lg border-2 border-dashed transition-all duration-200"
                   :class="addIsDragging ? 'scale-[1.01] border-primary bg-primary/5' : 'border-gray-300 bg-muted/30 hover:border-gray-400 hover:bg-muted/50 dark:border-gray-600'">
                <input ref="addFileInput" type="file" accept="image/*" :multiple="addRemainingSlots > 1" class="sr-only" @change="addOnFileInput"/>
                <div class="flex flex-col items-center justify-center gap-2 px-4 py-6">
                  <div class="rounded-full bg-muted p-3"><Upload class="h-5 w-5 text-muted-foreground"/></div>
                  <div class="text-center">
                    <p class="text-sm font-medium text-muted-foreground">
                      <span class="font-semibold text-primary">Click to upload</span> or drag & drop
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                      {{ addRemainingSlots }} image{{ addRemainingSlots > 1 ? 's' : '' }} remaining · JPG, PNG, WEBP
                    </p>
                  </div>
                </div>
              </div>
              <div v-else class="rounded-lg border border-dashed border-gray-200 bg-muted/20 px-4 py-3 text-center dark:border-gray-600">
                <p class="text-xs text-muted-foreground">Maximum of {{ MAX_IMAGES }} images reached</p>
              </div>
            </div>
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" @click="closeAddDialog" :disabled="addForm.processing">Cancel</Button>
            <Button type="submit" :disabled="addForm.processing">
              <svg v-if="addForm.processing" class="mr-2 h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
              {{ addForm.processing ? 'Saving...' : 'Save Product' }}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <!-- ─── Edit Product Dialog ─────────────────────────────────── -->
    <Dialog v-model:open="isEditDialogOpen">
      <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-[650px]"
                     @interact-outside="(e) => e.preventDefault()">
        <DialogHeader>
          <DialogTitle>Edit Product</DialogTitle>
          <DialogDescription>Update the product details below.</DialogDescription>
        </DialogHeader>

        <form @submit.prevent="handleEditSubmit">
          <div class="grid gap-4 py-4">
            <div class="grid grid-cols-2 gap-4">
              <div class="grid gap-2">
                <Label for="edit-name">Name</Label>
                <Input id="edit-name" v-model="editForm.name" placeholder="Product name" required/>
                <p v-if="editForm.errors.name" class="text-xs text-red-500">{{ editForm.errors.name }}</p>
              </div>
              <div class="grid gap-2">
                <Label for="edit-category">Category</Label>
                <Select v-model="editForm.category_id" required>
                  <SelectTrigger id="edit-category"><SelectValue placeholder="Select category"/></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="cat in categories" :key="cat.id" :value="cat.id.toString()">
                      {{ cat.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p v-if="editForm.errors.category_id" class="text-xs text-red-500">{{ editForm.errors.category_id }}</p>
              </div>
            </div>

            <div class="grid gap-2">
              <Label for="edit-description">Description</Label>
              <textarea id="edit-description" v-model="editForm.description" rows="3"
                        class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                        placeholder="Product description" required/>
              <p v-if="editForm.errors.description" class="text-xs text-red-500">{{ editForm.errors.description }}</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div class="grid gap-2">
                <Label for="edit-price">Price (KES)</Label>
                <Input id="edit-price" v-model="editForm.price" type="number" placeholder="0.00" min="0" required/>
                <p v-if="editForm.errors.price" class="text-xs text-red-500">{{ editForm.errors.price }}</p>
              </div>
              <div class="grid gap-2">
                <Label for="edit-stock">Stock</Label>
                <Input id="edit-stock" v-model="editForm.stock" type="number" placeholder="0" min="0" required/>
                <p v-if="editForm.errors.stock" class="text-xs text-red-500">{{ editForm.errors.stock }}</p>
              </div>
            </div>

            <div class="grid gap-2">
              <Label>Colors</Label>
              <div class="flex flex-wrap gap-2">
                <button v-for="color in availableColors" :key="color" type="button" @click="toggleEditColor(color)"
                        class="inline-flex rounded-full border px-3 py-1 text-xs font-medium transition-colors"
                        :class="editForm.colors.includes(color) ? 'border-primary bg-primary text-primary-foreground' : 'border-muted bg-muted text-muted-foreground hover:bg-muted/80'">
                  {{ color }}
                </button>
              </div>
              <p v-if="editForm.errors.colors" class="text-xs text-red-500">{{ editForm.errors.colors }}</p>
            </div>

            <div class="grid gap-2">
              <Label>Sizes</Label>
              <div class="flex flex-wrap gap-2">
                <button v-for="size in availableSizes" :key="size" type="button" @click="toggleEditSize(size)"
                        class="inline-flex rounded-md border px-3 py-1 text-xs font-medium transition-colors"
                        :class="editForm.sizes.includes(size) ? 'border-primary bg-primary text-primary-foreground' : 'border-muted bg-muted text-muted-foreground hover:bg-muted/80'">
                  {{ size }}
                </button>
              </div>
              <p v-if="editForm.errors.sizes" class="text-xs text-red-500">{{ editForm.errors.sizes }}</p>
            </div>

            <div class="grid gap-3">
              <div class="flex items-center justify-between">
                <Label>Product Images</Label>
                <span class="text-xs text-muted-foreground">{{ editTotalCount }} / {{ MAX_IMAGES }}</span>
              </div>
              <div v-if="editTotalCount > 0" class="grid grid-cols-3 gap-3">
                <div v-for="img in editExistingImages" :key="'existing-' + img.id"
                     class="group relative aspect-square overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                  <img :src="'/' + img.path" alt="Product" class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"/>
                  <div class="absolute inset-0 bg-black/0 transition-colors duration-200 group-hover:bg-black/40"/>
                  <button type="button" @click="removeExistingImage(img.id)"
                          class="absolute right-2 top-2 rounded-full bg-red-500 p-1 text-white opacity-0 transition-opacity duration-200 hover:bg-red-600 group-hover:opacity-100">
                    <X class="h-3 w-3"/>
                  </button>
                  <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/60 to-transparent px-2 py-1.5 opacity-0 transition-opacity duration-200 group-hover:opacity-100">
                    <p class="truncate text-xs text-white">Saved</p>
                  </div>
                </div>
                <div v-for="(preview, index) in editImagePreviews" :key="'new-' + index"
                     class="group relative aspect-square overflow-hidden rounded-lg border border-blue-300 ring-2 ring-blue-100 dark:border-blue-800 dark:ring-blue-900">
                  <img :src="preview.url" alt="Preview" class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"/>
                  <div class="absolute inset-0 bg-black/0 transition-colors duration-200 group-hover:bg-black/40"/>
                  <button type="button" @click="removeEditImage(index)"
                          class="absolute right-2 top-2 rounded-full bg-red-500 p-1 text-white opacity-0 transition-opacity duration-200 hover:bg-red-600 group-hover:opacity-100">
                    <X class="h-3 w-3"/>
                  </button>
                  <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/60 to-transparent px-2 py-1.5 opacity-0 transition-opacity duration-200 group-hover:opacity-100">
                    <p class="truncate text-xs text-white">New</p>
                  </div>
                </div>
              </div>
              <div v-if="editCanUpload"
                   @drop="editOnDrop" @dragover.prevent="editIsDragging = true" @dragleave="editIsDragging = false"
                   @click="($refs.editFileInput as HTMLInputElement).click()"
                   class="relative cursor-pointer rounded-lg border-2 border-dashed transition-all duration-200"
                   :class="editIsDragging ? 'scale-[1.01] border-primary bg-primary/5' : 'border-gray-300 bg-muted/30 hover:border-gray-400 hover:bg-muted/50 dark:border-gray-600'">
                <input ref="editFileInput" type="file" accept="image/*" :multiple="editRemainingSlots > 1" class="sr-only" @change="editOnFileInput"/>
                <div class="flex flex-col items-center justify-center gap-2 px-4 py-6">
                  <div class="rounded-full bg-muted p-3"><Upload class="h-5 w-5 text-muted-foreground"/></div>
                  <div class="text-center">
                    <p class="text-sm font-medium text-muted-foreground">
                      <span class="font-semibold text-primary">Click to upload</span> or drag & drop
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                      {{ editRemainingSlots }} image{{ editRemainingSlots > 1 ? 's' : '' }} remaining · JPG, PNG, WEBP
                    </p>
                  </div>
                </div>
              </div>
              <div v-else class="rounded-lg border border-dashed border-gray-200 bg-muted/20 px-4 py-3 text-center dark:border-gray-600">
                <p class="text-xs text-muted-foreground">Maximum of {{ MAX_IMAGES }} images reached</p>
              </div>
            </div>
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" @click="closeEditDialog" :disabled="editForm.processing">Cancel</Button>
            <Button type="submit" :disabled="editForm.processing">
              <svg v-if="editForm.processing" class="mr-2 h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
              {{ editForm.processing ? 'Saving...' : 'Save Changes' }}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <!-- ─── Delete Dialog ───────────────────────────────────────── -->
    <AlertDialog v-model:open="isDeleteDialogOpen">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Are you absolutely sure?</AlertDialogTitle>
          <AlertDialogDescription>
            This will permanently delete
            <span v-if="deletingProduct" class="font-semibold">{{ deletingProduct.name }}</span>.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel @click="closeDeleteDialog">Cancel</AlertDialogCancel>
          <AlertDialogAction @click="handleDeleteConfirm" class="bg-red-600 hover:bg-red-700">Delete</AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </AppLayout>
</template>