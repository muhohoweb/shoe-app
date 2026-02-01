<script setup lang="ts">
import { ref, computed } from 'vue'
import { Upload, X, Image } from 'lucide-vue-next'

const MAX_IMAGES = 3

const props = defineProps<{
  modelValue: File[]
  existingImages?: { id: number; path: string }[]
}>()

const emit = defineEmits<{
  'update:modelValue': [files: File[]]
  'remove-existing': [id: number]
}>()

const previews = computed(() =>
    props.modelValue.map((file) => ({
      name: file.name,
      url: URL.createObjectURL(file),
    }))
)

const removedExistingIds = ref<number[]>([])

const visibleExisting = computed(() =>
    (props.existingImages || []).filter((img) => !removedExistingIds.value.includes(img.id))
)

const totalCount = computed(() => visibleExisting.value.length + props.modelValue.length)
const canUpload = computed(() => totalCount.value < MAX_IMAGES)
const remainingSlots = computed(() => MAX_IMAGES - totalCount.value)

const isDragging = ref(false)

const handleFiles = (files: FileList | File[]) => {
  const allowed = remainingSlots.value
  const valid = Array.from(files)
      .slice(0, allowed)
      .filter((f) => f.type.startsWith('image/'))

  emit('update:modelValue', [...props.modelValue, ...valid])
}

const onDrop = (e: DragEvent) => {
  e.preventDefault()
  isDragging.value = false
  if (e.dataTransfer?.files) handleFiles(e.dataTransfer.files)
}

const onDragOver = (e: DragEvent) => {
  e.preventDefault()
  isDragging.value = true
}

const onDragLeave = () => {
  isDragging.value = false
}

const triggerInput = (input: HTMLInputElement) => {
  input.click()
}

const onFileInput = (e: Event) => {
  const input = e.target as HTMLInputElement
  if (input.files) handleFiles(input.files)
  input.value = ''
}

const removeNew = (index: number) => {
  const updated = [...props.modelValue]
  updated.splice(index, 1)
  emit('update:modelValue', updated)
}

const removeExisting = (id: number) => {
  removedExistingIds.value.push(id)
  emit('remove-existing', id)
}
</script>

<template>
  <div class="grid gap-3">
    <div class="flex items-center justify-between">
      <label class="text-sm font-medium text-muted-foreground">
        Product Images
      </label>
      <span class="text-xs text-muted-foreground">
        {{ totalCount }} / {{ MAX_IMAGES }}
      </span>
    </div>

    <!-- Preview Grid -->
    <div v-if="totalCount > 0" class="grid grid-cols-3 gap-3">
      <!-- Existing images -->
      <div
          v-for="img in visibleExisting"
          :key="'existing-' + img.id"
          class="group relative aspect-square overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700"
      >
        <img
            :src="'/' + img.path"
            alt="Product"
            class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
        />
        <div class="absolute inset-0 bg-black/0 transition-colors duration-200 group-hover:bg-black/40" />
        <button
            type="button"
            @click="removeExisting(img.id)"
            class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200 rounded-full bg-red-500 p-1 text-white hover:bg-red-600"
        >
          <X class="h-3 w-3" />
        </button>
        <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/60 to-transparent px-2 py-1.5 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
          <p class="text-xs text-white truncate">Saved</p>
        </div>
      </div>

      <!-- New preview images -->
      <div
          v-for="(preview, index) in previews"
          :key="'new-' + index"
          class="group relative aspect-square overflow-hidden rounded-lg border border-blue-300 dark:border-blue-800 ring-2 ring-blue-100 dark:ring-blue-900"
      >
        <img
            :src="preview.url"
            alt="Preview"
            class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
        />
        <div class="absolute inset-0 bg-black/0 transition-colors duration-200 group-hover:bg-black/40" />
        <button
            type="button"
            @click="removeNew(index)"
            class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200 rounded-full bg-red-500 p-1 text-white hover:bg-red-600"
        >
          <X class="h-3 w-3" />
        </button>
        <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/60 to-transparent px-2 py-1.5 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
          <p class="text-xs text-white truncate">New</p>
        </div>
      </div>
    </div>

    <!-- Dropzone -->
    <div
        v-if="canUpload"
        @drop="onDrop"
        @dragover="onDragOver"
        @dragleave="onDragLeave"
        class="relative rounded-lg border-2 border-dashed transition-colors duration-200 cursor-pointer"
        :class="[
        isDragging
          ? 'border-primary bg-primary/5 scale-[1.01]'
          : 'border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500 bg-muted/30 hover:bg-muted/50',
      ]"
        @click="triggerInput($refs.fileInput)"
    >
      <input
          ref="fileInput"
          type="file"
          accept="image/*"
          :multiple="remainingSlots > 1"
          class="sr-only"
          @change="onFileInput"
      />
      <div class="flex flex-col items-center justify-center gap-2 py-6 px-4">
        <div
            class="rounded-full bg-muted p-3 dark:bg-muted/50"
            :class="isDragging ? 'bg-primary/10' : ''"
        >
          <Upload class="h-5 w-5 text-muted-foreground" :class="isDragging ? 'text-primary' : ''" />
        </div>
        <div class="text-center">
          <p class="text-sm font-medium text-muted-foreground">
            <span class="text-primary font-semibold">Click to upload</span> or drag & drop
          </p>
          <p class="text-xs text-muted-foreground mt-0.5">
            {{ remainingSlots }} image{{ remainingSlots > 1 ? 's' : '' }} remaining · JPG, PNG, WEBP
          </p>
        </div>
      </div>
    </div>

    <!-- Full message -->
    <div v-else class="rounded-lg border border-dashed border-gray-200 dark:border-gray-600 bg-muted/20 py-3 px-4 text-center">
      <p class="text-xs text-muted-foreground">Maximum of {{ MAX_IMAGES }} images reached</p>
    </div>
  </div>
</template>