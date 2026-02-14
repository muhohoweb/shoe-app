<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import {dashboard} from '@/routes';
import {type BreadcrumbItem} from '@/types';
import {Head, router, useForm} from '@inertiajs/vue3';
import {Edit, Trash2, Plus, Upload, X} from 'lucide-vue-next';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import {Button} from '@/components/ui/button'
import {Input} from '@/components/ui/input'
import {Label} from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {toast} from 'vue-sonner'
import {ref, computed} from 'vue'
import Pagination from '@/components/Pagination.vue'

const props = defineProps<{
  products?: {
    data: any[]
    links: any[]
  }
  categories?: any[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  {title: 'Dashboard', href: dashboard().url},
  {title: 'Products'},
]

const productsList = computed(() => props.products?.data || [])
const paginationLinks = computed(() => props.products?.links || [])

const availableColors = [
  'white',
  'black',
  'grey',
  'dark tan',
  'mid brown',
  'light tan'
];

const availableSizes = ['20', '21', '22', '28', '29', '30', '31', '36', '37', '38', '39', '40', '41', '42', '43', '44','45',46,'47']

const MAX_IMAGES = 3

// ─── Image Upload Helpers ───────────────────────────────────────
interface ImagePreview {
  url: string
  name: string
}

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

const addImagePreviews = computed(() => buildPreviews(addForm.images))
const addCanUpload = computed(() => addForm.images.length < MAX_IMAGES)
const addRemainingSlots = computed(() => MAX_IMAGES - addForm.images.length)
const addIsDragging = ref(false)

const openAddDialog = () => {
  addForm.reset()
  addForm.clearErrors()
  isAddDialogOpen.value = true
}

const closeAddDialog = () => {
  isAddDialogOpen.value = false
  addForm.reset()
  addForm.clearErrors()
}

const toggleAddColor = (color: string) => {
  const i = addForm.colors.indexOf(color)
  i === -1 ? addForm.colors.push(color) : addForm.colors.splice(i, 1)
}

const toggleAddSize = (size: string) => {
  const i = addForm.sizes.indexOf(size)
  i === -1 ? addForm.sizes.push(size) : addForm.sizes.splice(i, 1)
}

function addHandleFiles(files: FileList | File[]) {
  const valid = Array.from(files)
      .slice(0, addRemainingSlots.value)
      .filter((f) => f.type.startsWith('image/'))
  addForm.images = [...addForm.images, ...valid]
}

const addOnDrop = (e: DragEvent) => {
  e.preventDefault()
  addIsDragging.value = false
  if (e.dataTransfer?.files) addHandleFiles(e.dataTransfer.files)
}

const addOnFileInput = (e: Event) => {
  const input = e.target as HTMLInputElement
  if (input.files) addHandleFiles(input.files)
  input.value = ''
}

const removeAddImage = (index: number) => {
  const updated = [...addForm.images]
  updated.splice(index, 1)
  addForm.images = updated
}

const handleAddSubmit = () => {
  addForm.post('/products', {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Product Added', {
        description: `${addForm.name} has been added successfully.`,
      })
      isAddDialogOpen.value = false
      addForm.reset()
    },
    onError: () => {
      toast.error('Validation Error', {
        description: 'Please check the form for errors.',
      })
    },
  })
}

// ─── Edit Modal ─────────────────────────────────────────────────
const isEditDialogOpen = ref(false)
const editingProduct = ref<any>(null)
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

const editExistingImages = computed(() =>
    (editingProduct.value?.images || []).filter(
        (img: any) => !editForm.removed_image_ids.includes(img.id)
    )
)

const editTotalCount = computed(() => editExistingImages.value.length + editForm.images.length)
const editCanUpload = computed(() => editTotalCount.value < MAX_IMAGES)
const editRemainingSlots = computed(() => MAX_IMAGES - editTotalCount.value)
const editIsDragging = ref(false)
const editImagePreviews = computed(() => buildPreviews(editForm.images))

const openEditDialog = (product: any) => {
  editingProduct.value = product
  editForm.id = product.id
  editForm.category_id = product.category_id.toString()
  editForm.name = product.name
  editForm.description = product.description
  editForm.price = product.price.toString()
  editForm.stock = product.stock.toString()
  editForm.colors = product.colors || []
  editForm.sizes = product.sizes || []
  editForm.images = []
  editForm.removed_image_ids = []
  editForm.clearErrors()
  isEditDialogOpen.value = true
}

const closeEditDialog = () => {
  isEditDialogOpen.value = false
  editingProduct.value = null
  editForm.reset()
  editForm.clearErrors()
}

const toggleEditColor = (color: string) => {
  const i = editForm.colors.indexOf(color)
  i === -1 ? editForm.colors.push(color) : editForm.colors.splice(i, 1)
}

const toggleEditSize = (size: string) => {
  const i = editForm.sizes.indexOf(size)
  i === -1 ? editForm.sizes.push(size) : editForm.sizes.splice(i, 1)
}

function editHandleFiles(files: FileList | File[]) {
  const valid = Array.from(files)
      .slice(0, editRemainingSlots.value)
      .filter((f) => f.type.startsWith('image/'))
  editForm.images = [...editForm.images, ...valid]
}

const editOnDrop = (e: DragEvent) => {
  e.preventDefault()
  editIsDragging.value = false
  if (e.dataTransfer?.files) editHandleFiles(e.dataTransfer.files)
}

const editOnFileInput = (e: Event) => {
  const input = e.target as HTMLInputElement
  if (input.files) editHandleFiles(input.files)
  input.value = ''
}

const removeEditImage = (index: number) => {
  const updated = [...editForm.images]
  updated.splice(index, 1)
  editForm.images = updated
}

const removeExistingImage = (id: number) => {
  editForm.removed_image_ids = [...editForm.removed_image_ids, id]
}

const handleEditSubmit = () => {
  editForm.put(`/products/${editForm.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Product Updated', {
        description: `${editForm.name} has been updated successfully.`,
      })
      isEditDialogOpen.value = false
      editingProduct.value = null
    },
    onError: () => {
      toast.error('Validation Error', {
        description: 'Please check the form for errors.',
      })
    },
  })
}

// ─── Delete Modal ───────────────────────────────────────────────
const isDeleteDialogOpen = ref(false)
const deletingProduct = ref<any>(null)

const openDeleteDialog = (product: any) => {
  deletingProduct.value = product
  isDeleteDialogOpen.value = true
}

const handleDeleteConfirm = () => {
  if (!deletingProduct.value) return

  router.delete(`/products/${deletingProduct.value.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Product Deleted', {
        description: `${deletingProduct.value.name} has been deleted successfully.`,
      })
      isDeleteDialogOpen.value = false
      deletingProduct.value = null
    },
    onError: () => {
      toast.error('Delete Failed', {
        description: 'Unable to delete product. Please try again.',
      })
    },
  })
}

const closeDeleteDialog = () => {
  isDeleteDialogOpen.value = false
  deletingProduct.value = null
}
</script>

<template>
  <Head title="Products"/>

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
      <div
          class="relative min-h-[100vh] flex-1 rounded-xl border border-gray-200 bg-white md:min-h-min dark:border-gray-700 dark:bg-gray-800">
        <div class="p-6">
          <div class="mb-4 flex items-center justify-between">
            <h2 class="text-xl font-semibold">Products</h2>
            <Button @click="openAddDialog">
              <Plus class="mr-2 h-4 w-4"/>
              Add Product
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
                  <TableCell colspan="7" class="text-center text-gray-500 dark:text-gray-400 h-24">
                    No products found.
                  </TableCell>
                </TableRow>
                <TableRow v-for="(product, index) in productsList" :key="product.id">
                  <TableCell class="font-medium">{{ index + 1 }}</TableCell>
                  <TableCell>
                    <div class="flex items-center gap-3">
                      <div
                          v-if="product.images && product.images.length > 0"
                          class="h-10 w-10 rounded-md overflow-hidden border border-gray-200 dark:border-gray-700 flex-shrink-0"
                      >
                        <img
                            :src="'/' + product.images[0].path"
                            alt=""
                            class="h-full w-full object-cover"
                        />
                      </div>
                      <div
                          v-else
                          class="h-10 w-10 rounded-md border border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center flex-shrink-0 bg-muted/50"
                      >
                        <Upload class="h-4 w-4 text-muted-foreground"/>
                      </div>
                      <span>{{ product.name }}</span>
                    </div>
                  </TableCell>
                  <TableCell class="text-gray-500 dark:text-gray-400">{{ product.category?.name }}</TableCell>
                  <TableCell>KES {{ Number(product.price).toLocaleString() }}</TableCell>
                  <TableCell>
                    <span
                        class="inline-flex rounded-full px-2 py-1 text-xs font-semibold"
                        :class="{
                        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': product.stock > 10,
                        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': product.stock > 0 && product.stock <= 10,
                        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': product.stock === 0,
                      }"
                    >
                      {{ product.stock }}
                    </span>
                  </TableCell>
                  <TableCell class="text-gray-500 dark:text-gray-400 text-sm">{{ product.sku }}</TableCell>
                  <TableCell class="text-right">
                    <div class="flex items-center justify-end gap-2">
                      <button
                          @click="openEditDialog(product)"
                          class="inline-flex items-center gap-1 rounded-md bg-blue-100 px-3 py-1.5 text-sm font-medium text-blue-700 hover:bg-blue-200 dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800"
                      >
                        <Edit class="h-4 w-4"/>
                        Edit
                      </button>
                      <button
                          @click="openDeleteDialog(product)"
                          class="inline-flex items-center gap-1 rounded-md bg-red-100 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-200 dark:bg-red-900 dark:text-red-200 dark:hover:bg-red-800"
                      >
                        <Trash2 class="h-4 w-4"/>
                        Delete
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
      <DialogContent class="sm:max-w-[650px] max-h-[90vh] overflow-y-auto"
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
              </div>
              <div class="grid gap-2">
                <Label for="add-category">Category</Label>
                <Select v-model="addForm.category_id" required>
                  <SelectTrigger id="add-category">
                    <SelectValue placeholder="Select category"/>
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="cat in categories" :key="cat.id" :value="cat.id.toString()">
                      {{ cat.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div class="grid gap-2">
              <Label for="add-description">Description</Label>
              <textarea
                  id="add-description"
                  v-model="addForm.description"
                  rows="3"
                  class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                  placeholder="Product description"
                  required
              />
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div class="grid gap-2">
                <Label for="add-price">Price (KES)</Label>
                <Input id="add-price" v-model="addForm.price" type="number" placeholder="0.00" min="0" required/>
              </div>
              <div class="grid gap-2">
                <Label for="add-stock">Stock</Label>
                <Input id="add-stock" v-model="addForm.stock" type="number" placeholder="0" min="0" required/>
              </div>
            </div>

            <!-- Colors -->
            <div class="grid gap-2">
              <Label>Colors</Label>
              <div class="flex flex-wrap gap-2">
                <button
                    v-for="color in availableColors"
                    :key="color"
                    type="button"
                    @click="toggleAddColor(color)"
                    class="inline-flex rounded-full px-3 py-1 text-xs font-medium border transition-colors"
                    :class="addForm.colors.includes(color)
                    ? 'bg-primary text-primary-foreground border-primary'
                    : 'bg-muted text-muted-foreground border-muted hover:bg-muted/80'"
                >
                  {{ color }}
                </button>
              </div>
            </div>

            <!-- Sizes -->
            <div class="grid gap-2">
              <Label>Sizes</Label>
              <div class="flex flex-wrap gap-2">
                <button
                    v-for="size in availableSizes"
                    :key="size"
                    type="button"
                    @click="toggleAddSize(size)"
                    class="inline-flex rounded-md px-3 py-1 text-xs font-medium border transition-colors"
                    :class="addForm.sizes.includes(size)
                    ? 'bg-primary text-primary-foreground border-primary'
                    : 'bg-muted text-muted-foreground border-muted hover:bg-muted/80'"
                >
                  {{ size }}
                </button>
              </div>
            </div>

            <!-- Images Upload -->
            <div class="grid gap-3">
              <div class="flex items-center justify-between">
                <Label>Product Images</Label>
                <span class="text-xs text-muted-foreground">{{ addForm.images.length }} / {{ MAX_IMAGES }}</span>
              </div>

              <!-- Previews -->
              <div v-if="addImagePreviews.length > 0" class="grid grid-cols-3 gap-3">
                <div
                    v-for="(preview, index) in addImagePreviews"
                    :key="index"
                    class="group relative aspect-square overflow-hidden rounded-lg border border-blue-300 dark:border-blue-800 ring-2 ring-blue-100 dark:ring-blue-900"
                >
                  <img :src="preview.url" alt="Preview"
                       class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"/>
                  <div class="absolute inset-0 bg-black/0 transition-colors duration-200 group-hover:bg-black/40"/>
                  <button
                      type="button"
                      @click="removeAddImage(index)"
                      class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200 rounded-full bg-red-500 p-1 text-white hover:bg-red-600"
                  >
                    <X class="h-3 w-3"/>
                  </button>
                  <div
                      class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/60 to-transparent px-2 py-1.5 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                    <p class="text-xs text-white truncate">New</p>
                  </div>
                </div>
              </div>

              <!-- Dropzone -->
              <div
                  v-if="addCanUpload"
                  @drop="addOnDrop"
                  @dragover.prevent="addIsDragging = true"
                  @dragleave="addIsDragging = false"
                  @click="$refs.addFileInput.click()"
                  class="relative rounded-lg border-2 border-dashed transition-all duration-200 cursor-pointer"
                  :class="[
                  addIsDragging
                    ? 'border-primary bg-primary/5 scale-[1.01]'
                    : 'border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500 bg-muted/30 hover:bg-muted/50',
                ]"
              >
                <input
                    ref="addFileInput"
                    type="file"
                    accept="image/*"
                    :multiple="addRemainingSlots > 1"
                    class="sr-only"
                    @change="addOnFileInput"
                />
                <div class="flex flex-col items-center justify-center gap-2 py-6 px-4">
                  <div class="rounded-full bg-muted p-3 dark:bg-muted/50" :class="addIsDragging ? 'bg-primary/10' : ''">
                    <Upload class="h-5 w-5 text-muted-foreground" :class="addIsDragging ? 'text-primary' : ''"/>
                  </div>
                  <div class="text-center">
                    <p class="text-sm font-medium text-muted-foreground">
                      <span class="text-primary font-semibold">Click to upload</span> or drag & drop
                    </p>
                    <p class="text-xs text-muted-foreground mt-0.5">
                      {{ addRemainingSlots }} image{{ addRemainingSlots > 1 ? 's' : '' }} remaining · JPG, PNG, WEBP
                    </p>
                  </div>
                </div>
              </div>

              <div v-else
                   class="rounded-lg border border-dashed border-gray-200 dark:border-gray-600 bg-muted/20 py-3 px-4 text-center">
                <p class="text-xs text-muted-foreground">Maximum of {{ MAX_IMAGES }} images reached</p>
              </div>
            </div>
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" @click="closeAddDialog">Cancel</Button>
            <Button type="submit">Save Product</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <!-- ─── Edit Product Dialog ─────────────────────────────────── -->
    <Dialog v-model:open="isEditDialogOpen">
      <DialogContent class="sm:max-w-[650px] max-h-[90vh] overflow-y-auto"
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
              </div>
              <div class="grid gap-2">
                <Label for="edit-category">Category</Label>
                <Select v-model="editForm.category_id" required>
                  <SelectTrigger id="edit-category">
                    <SelectValue placeholder="Select category"/>
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="cat in categories" :key="cat.id" :value="cat.id.toString()">
                      {{ cat.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div class="grid gap-2">
              <Label for="edit-description">Description</Label>
              <textarea
                  id="edit-description"
                  v-model="editForm.description"
                  rows="3"
                  class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                  placeholder="Product description"
                  required
              />
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div class="grid gap-2">
                <Label for="edit-price">Price (KES)</Label>
                <Input id="edit-price" v-model="editForm.price" type="number" placeholder="0.00" min="0" required/>
              </div>
              <div class="grid gap-2">
                <Label for="edit-stock">Stock</Label>
                <Input id="edit-stock" v-model="editForm.stock" type="number" placeholder="0" min="0" required/>
              </div>
            </div>

            <!-- Colors -->
            <div class="grid gap-2">
              <Label>Colors</Label>
              <div class="flex flex-wrap gap-2">
                <button
                    v-for="color in availableColors"
                    :key="color"
                    type="button"
                    @click="toggleEditColor(color)"
                    class="inline-flex rounded-full px-3 py-1 text-xs font-medium border transition-colors"
                    :class="editForm.colors.includes(color)
                    ? 'bg-primary text-primary-foreground border-primary'
                    : 'bg-muted text-muted-foreground border-muted hover:bg-muted/80'"
                >
                  {{ color }}
                </button>
              </div>
            </div>

            <!-- Sizes -->
            <div class="grid gap-2">
              <Label>Sizes</Label>
              <div class="flex flex-wrap gap-2">
                <button
                    v-for="size in availableSizes"
                    :key="size"
                    type="button"
                    @click="toggleEditSize(size)"
                    class="inline-flex rounded-md px-3 py-1 text-xs font-medium border transition-colors"
                    :class="editForm.sizes.includes(size)
                    ? 'bg-primary text-primary-foreground border-primary'
                    : 'bg-muted text-muted-foreground border-muted hover:bg-muted/80'"
                >
                  {{ size }}
                </button>
              </div>
            </div>

            <!-- Images Upload -->
            <div class="grid gap-3">
              <div class="flex items-center justify-between">
                <Label>Product Images</Label>
                <span class="text-xs text-muted-foreground">{{ editTotalCount }} / {{ MAX_IMAGES }}</span>
              </div>

              <!-- Previews -->
              <div v-if="editTotalCount > 0" class="grid grid-cols-3 gap-3">
                <!-- Existing saved images -->
                <div
                    v-for="img in editExistingImages"
                    :key="'existing-' + img.id"
                    class="group relative aspect-square overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700"
                >
                  <img :src="'/' + img.path" alt="Product"
                       class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"/>
                  <div class="absolute inset-0 bg-black/0 transition-colors duration-200 group-hover:bg-black/40"/>
                  <button
                      type="button"
                      @click="removeExistingImage(img.id)"
                      class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200 rounded-full bg-red-500 p-1 text-white hover:bg-red-600"
                  >
                    <X class="h-3 w-3"/>
                  </button>
                  <div
                      class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/60 to-transparent px-2 py-1.5 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                    <p class="text-xs text-white truncate">Saved</p>
                  </div>
                </div>

                <!-- New preview images -->
                <div
                    v-for="(preview, index) in editImagePreviews"
                    :key="'new-' + index"
                    class="group relative aspect-square overflow-hidden rounded-lg border border-blue-300 dark:border-blue-800 ring-2 ring-blue-100 dark:ring-blue-900"
                >
                  <img :src="preview.url" alt="Preview"
                       class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"/>
                  <div class="absolute inset-0 bg-black/0 transition-colors duration-200 group-hover:bg-black/40"/>
                  <button
                      type="button"
                      @click="removeEditImage(index)"
                      class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200 rounded-full bg-red-500 p-1 text-white hover:bg-red-600"
                  >
                    <X class="h-3 w-3"/>
                  </button>
                  <div
                      class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/60 to-transparent px-2 py-1.5 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                    <p class="text-xs text-white truncate">New</p>
                  </div>
                </div>
              </div>

              <!-- Dropzone -->
              <div
                  v-if="editCanUpload"
                  @drop="editOnDrop"
                  @dragover.prevent="editIsDragging = true"
                  @dragleave="editIsDragging = false"
                  @click="$refs.editFileInput.click()"
                  class="relative rounded-lg border-2 border-dashed transition-all duration-200 cursor-pointer"
                  :class="[
                  editIsDragging
                    ? 'border-primary bg-primary/5 scale-[1.01]'
                    : 'border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500 bg-muted/30 hover:bg-muted/50',
                ]"
              >
                <input
                    ref="editFileInput"
                    type="file"
                    accept="image/*"
                    :multiple="editRemainingSlots > 1"
                    class="sr-only"
                    @change="editOnFileInput"
                />
                <div class="flex flex-col items-center justify-center gap-2 py-6 px-4">
                  <div class="rounded-full bg-muted p-3 dark:bg-muted/50"
                       :class="editIsDragging ? 'bg-primary/10' : ''">
                    <Upload class="h-5 w-5 text-muted-foreground" :class="editIsDragging ? 'text-primary' : ''"/>
                  </div>
                  <div class="text-center">
                    <p class="text-sm font-medium text-muted-foreground">
                      <span class="text-primary font-semibold">Click to upload</span> or drag & drop
                    </p>
                    <p class="text-xs text-muted-foreground mt-0.5">
                      {{ editRemainingSlots }} image{{ editRemainingSlots > 1 ? 's' : '' }} remaining · JPG, PNG, WEBP
                    </p>
                  </div>
                </div>
              </div>

              <div v-else
                   class="rounded-lg border border-dashed border-gray-200 dark:border-gray-600 bg-muted/20 py-3 px-4 text-center">
                <p class="text-xs text-muted-foreground">Maximum of {{ MAX_IMAGES }} images reached</p>
              </div>
            </div>
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" @click="closeEditDialog">Cancel</Button>
            <Button type="submit">Save Changes</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <!-- ─── Delete Product Alert Dialog ─────────────────────────── -->
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
          <AlertDialogAction @click="handleDeleteConfirm" class="bg-red-600 hover:bg-red-700">
            Delete
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </AppLayout>
</template>