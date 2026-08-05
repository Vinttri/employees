<template>
	<NcAppContent name="Example – Dashboard">
		<div class="wrap">
			<div class="main-content">
				<div class="toolbar toolbar-mobile">
					<button type="button" class="zoom-btn" @click="zoomOut">
						-
					</button>
					<span class="zoom-label">{{ Math.round(effectiveScale * 100) }}%</span>
					<button type="button" class="zoom-btn" @click="zoomIn">
						+
					</button>
					<button type="button" class="zoom-btn" @click="resetView">
						Reset
					</button>
				</div>

				<div ref="viewport" class="parking-viewport">
					<div
						ref="parkingContainer"
						class="parking-stage"
						:class="{ dragging: isDragging }">
						<div
							ref="parking"
							class="parking"
							:style="parkingStyle">
							<div class="side">
								<div class="slot medium empty top space" @click.stop="handleSlotClick(1)">
									{{ spaces[0].name || spaces[0].status }}
								</div>
								<div class="slot small" @click.stop="handleSlotClick(2)">
									{{ spaces[1].name || spaces[1].status }}
								</div>
								<div class="slot small space" @click.stop="handleSlotClick(3)">
									{{ spaces[2].name || spaces[2].status }}
								</div>
								<div class="slot small" @click.stop="handleSlotClick(4)">
									{{ spaces[3].name || spaces[3].status }}
								</div>
								<div class="slot small space" @click.stop="handleSlotClick(5)">
									{{ spaces[4].name || spaces[4].status }}
								</div>
								<div class="slot small" @click.stop="handleSlotClick(6)">
									{{ spaces[5].name || spaces[5].status }}
								</div>
								<div class="slot small" @click.stop="handleSlotClick(7)">
									{{ spaces[6].name || spaces[6].status }}
								</div>
								<div class="slot big space-big" @click.stop="handleSlotClick(8)">
									{{ spaces[7].name || spaces[7].status }}
								</div>
								<div class="slot small" @click.stop="handleSlotClick(9)">
									{{ spaces[8].name || spaces[8].status }}
								</div>
								<div class="slot small space" @click.stop="handleSlotClick(10)">
									{{ spaces[9].name || spaces[9].status }}
								</div>
								<div class="slot small" @click.stop="handleSlotClick(11)">
									{{ spaces[10].name || spaces[10].status }}
								</div>
								<div class="slot small" @click.stop="handleSlotClick(12)">
									{{ spaces[11].name || spaces[11].status }}
								</div>
							</div>

							<div class="center">
								<div class="top-grid">
									<div class="slot empty" @click.stop="handleSlotClick(13)">
										{{ spaces[12].name || spaces[12].status }}
									</div>
									<div class="slot empty" @click.stop="handleSlotClick(20)">
										{{ spaces[19].name || spaces[19].status }}
									</div>
									<div class="slot empty" @click.stop="handleSlotClick(23)">
										{{ spaces[22].name || spaces[22].status }}
									</div>
									<div class="slot empty" @click.stop="handleSlotClick(14)">
										{{ spaces[13].name || spaces[13].status }}
									</div>
									<div class="slot empty" @click.stop="handleSlotClick(21)">
										{{ spaces[20].name || spaces[20].status }}
									</div>
									<div class="slot empty" @click.stop="handleSlotClick(24)">
										{{ spaces[23].name || spaces[23].status }}
									</div>
									<div class="slot empty" @click.stop="handleSlotClick(15)">
										{{ spaces[14].name || spaces[14].status }}
									</div>
									<div class="slot empty" @click.stop="handleSlotClick(22)">
										{{ spaces[21].name || spaces[21].status }}
									</div>
									<div class="slot empty" @click.stop="handleSlotClick(25)">
										{{ spaces[24].name || spaces[24].status }}
									</div>
									<div class="slot empty" @click.stop="handleSlotClick(16)">
										{{ spaces[15].name || spaces[15].status }}
									</div>
									<div class="slot empty hidden-spot">
						&nbsp;
									</div>
									<div class="slot empty" @click.stop="handleSlotClick(26)">
										{{ spaces[25].name || spaces[25].status }}
									</div>
									<div class="slot empty" @click.stop="handleSlotClick(17)">
										{{ spaces[16].name || spaces[16].status }}
									</div>
									<div class="slot empty hidden-spot" />
									<div class="slot empty" @click.stop="handleSlotClick(27)">
										{{ spaces[26].name || spaces[26].status }}
									</div>
									<div class="slot empty" @click.stop="handleSlotClick(18)">
										{{ spaces[17].name || spaces[17].status }}
									</div>
									<div class="slot empty hidden-spot" />
									<div class="slot empty" @click.stop="handleSlotClick(28)">
										{{ spaces[27].name || spaces[27].status }}
									</div>
									<div class="slot empty" @click.stop="handleSlotClick(19)">
										{{ spaces[18].name || spaces[18].status }}
									</div>
									<div class="slot empty hidden-spot" />
									<div class="slot empty" @click.stop="handleSlotClick(29)">
										{{ spaces[28].name || spaces[28].status }}
									</div>
								</div>

								<div class="road">
									<div class="car">
										entrada
									</div>
								</div>
							</div>

							<div class="side">
								<div class="slot medium empty top space" @click.stop="handleSlotClick(30)">
									{{ spaces[29].name || spaces[29].status }}
								</div>
								<div class="slot small empty" @click.stop="handleSlotClick(31)">
									{{ spaces[30].name || spaces[30].status }}
								</div>
								<div class="slot small space" @click.stop="handleSlotClick(32)">
									{{ spaces[31].name || spaces[31].status }}
								</div>
								<div class="slot small" @click.stop="handleSlotClick(33)">
									{{ spaces[32].name || spaces[32].status }}
								</div>
								<div class="slot small space" @click.stop="handleSlotClick(34)">
									{{ spaces[33].name || spaces[33].status }}
								</div>
								<div class="slot small" @click.stop="handleSlotClick(35)">
									{{ spaces[34].name || spaces[34].status }}
								</div>
								<div class="slot small space" @click.stop="handleSlotClick(36)">
									{{ spaces[35].name || spaces[35].status }}
								</div>
								<div class="slot small" @click.stop="handleSlotClick(37)">
									{{ spaces[36].name || spaces[36].status }}
								</div>
								<div class="slot small space" @click.stop="handleSlotClick(38)">
									{{ spaces[37].name || spaces[37].status }}
								</div>
								<div class="slot small" @click.stop="handleSlotClick(39)">
									{{ spaces[38].name || spaces[38].status }}
								</div>
								<div class="slot small space" @click.stop="handleSlotClick(40)">
									{{ spaces[39].name || spaces[39].status }}
								</div>
								<div class="slot small" @click.stop="handleSlotClick(41)">
									{{ spaces[40].name || spaces[40].status }}
								</div>
								<div class="slot small" @click.stop="handleSlotClick(42)">
									{{ spaces[41].name || spaces[41].status }}
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="side-navigator">
				<div class="toolbar">
					<button type="button" class="zoom-btn" @click="zoomOut">
						-
					</button>
					<span class="zoom-label">{{ Math.round(effectiveScale * 100) }}%</span>
					<button type="button" class="zoom-btn" @click="zoomIn">
						+
					</button>
					<button type="button" class="zoom-btn" @click="resetView">
						Reset
					</button>
				</div>
				<p class="helper-text">
					Mapa fijo del estacionamiento. En móvil se escala completo sin alterar proporciones.
				</p>
			</div>
		</div>
	</NcAppContent>
</template>

<script>
import { NcAppContent } from '@nextcloud/vue'
import { t } from '@nextcloud/l10n'
import { showSuccess } from '@nextcloud/dialogs'

const MAP_WIDTH = 760
const MAP_HEIGHT = 1120

export default {
	name: 'Example',
	components: {
		NcAppContent,
	},
	data() {
		return {
			loading: true,
			zoomLevel: 1,
			maxZoom: 3,
			minZoom: 0.6,
			baseScale: 1,
			translateX: 0,
			translateY: 0,
			isDragging: false,
			startX: 0,
			startY: 0,
			dragStartX: 0,
			dragStartY: 0,
			activePointerId: null,
			resizeObserver: null,
			spaces: Array.from({ length: 42 }, (_, index) => ({
				id: index + 1,
				numero: index + 1,
				name: '',
				status: 'vacio',
			})),
		}
	},
	computed: {
		effectiveScale() {
			return this.baseScale * this.zoomLevel
		},
		parkingStyle() {
			return {
				width: `${MAP_WIDTH}px`,
				height: `${MAP_HEIGHT}px`,
				transform: `translate(${this.translateX}px, ${this.translateY}px) scale(${this.effectiveScale})`,
				transformOrigin: 'center center',
			}
		},
	},
	mounted() {
		this.bindDragEvents()
		this.setupResizeObserver()
		this.updateBaseScale()
	},
	beforeUnmount() {
		this.unbindDragEvents()
		if (this.resizeObserver) {
			this.resizeObserver.disconnect()
		}
	},
	methods: {
		t,
		handleSlotClick(slotNumber) {
			// eslint-disable-next-line no-console
			console.log(`Espacio ${slotNumber}`)
			showSuccess(slotNumber)
		},
		setupResizeObserver() {
			const viewport = this.$refs.viewport
			if (!viewport || typeof ResizeObserver === 'undefined') {
				window.addEventListener('resize', this.updateBaseScale)
				return
			}

			this.resizeObserver = new ResizeObserver(() => {
				this.updateBaseScale()
			})
			this.resizeObserver.observe(viewport)
		},
		updateBaseScale() {
			const viewport = this.$refs.viewport
			if (!viewport) {
				return
			}

			const padding = 24
			const availableWidth = Math.max(viewport.clientWidth - padding, 200)
			const availableHeight = Math.max(viewport.clientHeight - padding, 200)
			const widthScale = availableWidth / MAP_WIDTH
			const heightScale = availableHeight / MAP_HEIGHT
			this.baseScale = Math.min(widthScale, heightScale, 1)
		},
		bindDragEvents() {
			const parkingContainer = this.$refs.parkingContainer
			if (!parkingContainer) {
				return
			}

			parkingContainer.addEventListener('pointerdown', this.onPointerDown, { passive: false })
			document.addEventListener('pointermove', this.onPointerMove, { passive: false })
			document.addEventListener('pointerup', this.onPointerUp)
			document.addEventListener('pointercancel', this.onPointerUp)
		},
		unbindDragEvents() {
			const parkingContainer = this.$refs.parkingContainer
			if (parkingContainer) {
				parkingContainer.removeEventListener('pointerdown', this.onPointerDown)
			}

			document.removeEventListener('pointermove', this.onPointerMove)
			document.removeEventListener('pointerup', this.onPointerUp)
			document.removeEventListener('pointercancel', this.onPointerUp)
			window.removeEventListener('resize', this.updateBaseScale)
		},
		onPointerDown(e) {
			const parking = this.$refs.parking
			const parkingContainer = this.$refs.parkingContainer

			if (!parking || !parking.contains(e.target)) {
				return
			}

			// Si se hizo click en un slot, deja que el click funcione normal
			if (e.target.closest('.slot')) {
				return
			}

			if (e.pointerType === 'mouse' && e.button !== 0) {
				return
			}

			e.preventDefault()
			this.isDragging = true
			this.activePointerId = e.pointerId
			this.startX = e.clientX
			this.startY = e.clientY
			this.dragStartX = this.translateX
			this.dragStartY = this.translateY

			if (parkingContainer && parkingContainer.setPointerCapture) {
				parkingContainer.setPointerCapture(e.pointerId)
			}
		},
		onPointerMove(e) {
			if (!this.isDragging || this.activePointerId !== e.pointerId) {
				return
			}

			e.preventDefault()
			this.translateX = this.dragStartX + (e.clientX - this.startX)
			this.translateY = this.dragStartY + (e.clientY - this.startY)
		},
		onPointerUp(e) {
			if (this.activePointerId !== null && e.pointerId !== this.activePointerId) {
				return
			}

			this.isDragging = false
			this.activePointerId = null
		},
		zoomIn() {
			if (this.zoomLevel < this.maxZoom) {
				this.zoomLevel = Math.min(this.zoomLevel + 0.2, this.maxZoom)
			}
		},
		zoomOut() {
			if (this.zoomLevel > this.minZoom) {
				this.zoomLevel = Math.max(this.zoomLevel - 0.2, this.minZoom)
			}
		},
		resetView() {
			this.zoomLevel = 1
			this.translateX = 0
			this.translateY = 0
			this.updateBaseScale()
		},
	},
}
</script>

<style scoped lang="scss">
.wrap {
	display: grid;
	grid-template-columns: minmax(0, 1fr) 280px;
	gap: 20px;
	width: 100%;
}

.main-content,
.side-navigator {
	background: var(--color-background-darker);
	border: 1px solid var(--color-border);
	border-radius: 12px;
}

.main-content {
	padding: 16px;
	min-height: calc(100vh - 140px);
	overflow: hidden;
}

.side-navigator {
	padding: 20px;
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.toolbar {
	display: flex;
	align-items: center;
	gap: 10px;
	flex-wrap: wrap;
}

.toolbar-mobile {
	display: none;
	margin-bottom: 12px;
}

.zoom-btn {
	padding: 8px 12px;
	border: 1px solid var(--color-border);
	border-radius: 8px;
	background: var(--color-main-background);
	cursor: pointer;
	min-width: 44px;
}

.zoom-label {
	font-weight: 600;
	min-width: 56px;
	text-align: center;
}

.helper-text {
	margin: 0;
	font-size: 14px;
	line-height: 1.4;
	color: var(--color-text-maxcontrast);
}

.parking-viewport {
	position: relative;
	width: 100%;
	height: calc(100vh - 190px);
	min-height: 420px;
	overflow: hidden;
	border-radius: 12px;
	background: var(--color-main-background);
}

.parking-stage {
	position: absolute;
	inset: 0;
	display: flex;
	justify-content: center;
	align-items: center;
	user-select: none;
	touch-action: none;
	cursor: grab;
	overflow: hidden;
}

.parking-stage.dragging {
	cursor: grabbing;
}

.parking {
	background: #dcdcdc;
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	gap: 20px;
	padding: 10px;
	box-sizing: border-box;
	will-change: transform;
}

.side {
	width: 170px;
	display: flex;
	flex-direction: column;
	gap: 3.5px;
}

.slot {
	background: #efefef;
	border: 1px solid #222;
	box-sizing: border-box;
	display: flex;
	align-items: center;
	justify-content: center;
	text-align: center;
	color: #000;
	font-size: 18px;
	min-height: 78px;
}

.slot.small {
	min-height: 70px;
}

.slot.medium {
	min-height: 90px;
}

.slot.big {
	min-height: 120px;
}

.slot.tall {
	min-height: 120px;
}

.slot.empty {
	color: #d10000;
	font-size: 22px;
	font-weight: 600;
}

.space{
	margin-bottom: 5px;
}
.space-big{
	margin-bottom: 15px;
	margin-top: 15px;
}
.hidden-spot{
	visibility: hidden;
}
.center {
	flex: 1;
	min-height: 1100px;
	display: flex;
	flex-direction: column;
	align-items: center;
}

.top-grid {
	display: grid;
	grid-template-columns: repeat(3, 80px);
	grid-template-rows: repeat(3, 140px);
	gap: 4px;
	margin-top: 0;
}

.top-grid .slot {
	min-height: auto;
	height: 140px;
	width: 80px;
}

.road {
	flex: 1;
	width: 100%;
	position: relative;
}

.car {
	position: absolute;
	bottom: 40px;
	left: 50%;
	transform: translateX(-50%);
	font-size: 40px;
	line-height: 1;
}

@media (max-width: 1024px) {
	.wrap {
		grid-template-columns: 1fr;
	}

	.side-navigator {
		display: none;
	}

	.toolbar-mobile {
		display: flex;
	}

	.main-content {
		min-height: calc(100vh - 110px);
		padding: 12px;
	}

	.parking-viewport {
		height: calc(100vh - 180px);
		min-height: 360px;
	}
}

@media (max-width: 640px) {
	.main-content {
		padding: 10px;
	}

	.parking-viewport {
		min-height: 300px;
		height: calc(100vh - 170px);
	}

	.toolbar {
		gap: 8px;
	}

	.zoom-btn {
		padding: 10px 12px;
	}
}
</style>
