<template>
	<div class="short-videos-container">
		<!-- Loading State -->
		<div v-if="loading && videos.length === 0" class="loading-state">
			<div class="spinner"></div>
			<p>Loading videos...</p>
		</div>
		
		<!-- Empty State -->
		<div v-else-if="!loading && videos.length === 0" class="empty-state">
			<i class="fas fa-video fa-3x"></i>
			<h3>No Videos Available</h3>
			<p>There are no short videos to display at the moment.</p>
		</div>
		
		<!-- Videos -->
		<div v-else class="videos-wrapper" ref="videosWrapper">
			<div 
				v-for="(video, index) in videos" 
				:key="`video-${video.id}`"
				class="video-item"
				:class="{ 'active': currentVideoIndex === index }"
				@click="playPauseVideo(index)"
			>
				<!-- Video Element -->
				<video 
					v-if="video.media_attachments[0].type === 'video'"
					:ref="`video-${index}`"
					:src="video.media_attachments[0].url"
					:poster="video.media_attachments[0].preview_url"
					class="video-player"
					loop
					muted
					playsinline
					@ended="nextVideo"
					@loadedmetadata="onVideoLoaded(index)"
				></video>
				
				<!-- Image Element -->
				<img 
					v-else-if="video.media_attachments[0].type === 'image'"
					:src="video.media_attachments[0].url"
					:alt="video.content || 'Image'"
					class="image-player"
					@load="onVideoLoaded(index)"
				>

				<!-- Video Overlay -->
				<div class="video-overlay">
					<!-- User Info -->
					<div class="user-info">
						<img 
							:src="video.account.avatar" 
							:alt="video.account.username"
							class="user-avatar"
							@error="handleAvatarError"
						>
						<div class="user-details">
							<h4 class="username">@{{ video.account.username }}</h4>
							<p class="display-name">{{ video.account.display_name }}</p>
						</div>
					</div>

					<!-- Video Caption -->
					<div class="video-caption" v-if="video.content">
						<p v-html="video.content"></p>
					</div>

					<!-- Interaction Buttons -->
					<div class="interaction-buttons">
						<!-- Like Button -->
						<div class="interaction-item">
							<button 
								@click.stop="toggleLike(video)"
								class="interaction-btn"
								:class="{ 'liked': video.favourited }"
							>
								<i class="fas fa-heart"></i>
							</button>
							<span class="interaction-count">{{ formatCount(video.favourites_count) }}</span>
						</div>

						<!-- Comment Button -->
						<div class="interaction-item">
							<button 
								@click.stop="openComments(video)"
								class="interaction-btn"
							>
								<i class="fas fa-comment"></i>
							</button>
							<span class="interaction-count">{{ formatCount(video.replies_count) }}</span>
						</div>

						<!-- Share Button -->
						<div class="interaction-item">
							<button 
								@click.stop="shareVideo(video)"
								class="interaction-btn"
							>
								<i class="fas fa-share"></i>
							</button>
							<span class="interaction-count">{{ formatCount(video.reblogs_count) }}</span>
						</div>

						<!-- Bookmark Button -->
						<div class="interaction-item">
							<button 
								@click.stop="toggleBookmark(video)"
								class="interaction-btn"
								:class="{ 'bookmarked': video.bookmarked }"
							>
								<i class="fas fa-bookmark"></i>
							</button>
						</div>
					</div>
				</div>

				<!-- Play/Pause Indicator -->
				<div class="play-indicator" v-show="!isPlaying && currentVideoIndex === index">
					<i class="fas fa-play"></i>
				</div>

				<!-- Loading Indicator -->
				<div class="loading-indicator" v-show="loading && currentVideoIndex === index">
					<div class="spinner"></div>
				</div>
			</div>
		</div>

		<!-- Navigation Dots -->
		<div class="navigation-dots" v-if="videos.length > 1">
			<div 
				v-for="(video, index) in videos" 
				:key="`dot-${index}`"
				class="nav-dot"
				:class="{ 'active': currentVideoIndex === index }"
				@click="goToVideo(index)"
			></div>
		</div>

		<!-- Comments Modal -->
		<div v-if="showComments" class="comments-modal" @click="closeComments">
			<div class="comments-content" @click.stop>
				<div class="comments-header">
					<h3>Comments</h3>
					<button @click="closeComments" class="close-btn">
						<i class="fas fa-times"></i>
					</button>
				</div>
				<div class="comments-list">
					<!-- Comments will be loaded here -->
					<div v-if="loadingComments" class="loading-comments">
						<div class="spinner"></div>
					</div>
					<div v-else-if="comments.length === 0" class="no-comments">
						<p>No comments yet. Be the first to comment!</p>
					</div>
					<div v-else>
						<div v-for="comment in comments" :key="comment.id" class="comment-item">
							<img :src="comment.account.avatar" :alt="comment.account.username" class="comment-avatar">
							<div class="comment-content">
								<strong>{{ comment.account.username }}</strong>
								<p v-html="comment.content"></p>
								<small>{{ formatDate(comment.created_at) }}</small>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
export default {
	name: 'ShortVideos',
	props: {
		initialVideos: {
			type: Array,
			default: () => []
		}
	},
	data() {
		return {
			videos: [],
			currentVideoIndex: 0,
			isPlaying: false,
			loading: false,
			showComments: false,
			comments: [],
			loadingComments: false,
			selectedVideo: null,
			touchStartY: 0,
			touchEndY: 0,
			isScrolling: false
		}
	},
	mounted() {
		this.videos = this.initialVideos;
		this.setupTouchEvents();
		this.setupKeyboardEvents();
		
		// If no initial videos provided, load from API
		if (this.initialVideos.length === 0) {
			this.loadVideosFromAPI();
		} else {
			this.loadMoreVideos();
		}
		
		// Auto-play first video
		if (this.videos.length > 0) {
			this.$nextTick(() => {
				this.playVideo(0);
			});
		}
	},
	beforeDestroy() {
		this.removeTouchEvents();
		this.removeKeyboardEvents();
	},
	methods: {
		async loadVideosFromAPI() {
			this.loading = true;
			try {
				// Load media from public timeline API
				const response = await axios.get('/api/v1/timelines/public?limit=20');
				const videos = response.data.filter(post => 
					post.media_attachments && 
					post.media_attachments.length > 0 && 
					(post.media_attachments[0].type === 'video' || post.media_attachments[0].type === 'image')
				);
				
				this.videos = videos;
				
				// Auto-play first video if available
				if (this.videos.length > 0) {
					this.$nextTick(() => {
						this.playVideo(0);
					});
				}
			} catch (error) {
				console.error('Error loading videos:', error);
				// Show empty state or error message
				this.videos = [];
			} finally {
				this.loading = false;
			}
		},
		
		setupTouchEvents() {
			const wrapper = this.$refs.videosWrapper;
			if (wrapper) {
				wrapper.addEventListener('touchstart', this.handleTouchStart, { passive: true });
				wrapper.addEventListener('touchend', this.handleTouchEnd, { passive: true });
				wrapper.addEventListener('wheel', this.handleWheel, { passive: false });
			}
		},
		
		removeTouchEvents() {
			const wrapper = this.$refs.videosWrapper;
			if (wrapper) {
				wrapper.removeEventListener('touchstart', this.handleTouchStart);
				wrapper.removeEventListener('touchend', this.handleTouchEnd);
				wrapper.removeEventListener('wheel', this.handleWheel);
			}
		},
		
		setupKeyboardEvents() {
			document.addEventListener('keydown', this.handleKeyDown);
		},
		
		removeKeyboardEvents() {
			document.removeEventListener('keydown', this.handleKeyDown);
		},
		
		handleTouchStart(e) {
			this.touchStartY = e.touches[0].clientY;
		},
		
		handleTouchEnd(e) {
			this.touchEndY = e.changedTouches[0].clientY;
			this.handleSwipe();
		},
		
		handleWheel(e) {
			e.preventDefault();
			if (this.isScrolling) return;
			
			this.isScrolling = true;
			setTimeout(() => {
				this.isScrolling = false;
			}, 500);
			
			if (e.deltaY > 0) {
				this.nextVideo();
			} else {
				this.previousVideo();
			}
		},
		
		handleSwipe() {
			const swipeThreshold = 50;
			const diff = this.touchStartY - this.touchEndY;
			
			if (Math.abs(diff) > swipeThreshold) {
				if (diff > 0) {
					this.nextVideo();
				} else {
					this.previousVideo();
				}
			}
		},
		
		handleKeyDown(e) {
			switch(e.key) {
				case 'ArrowUp':
					e.preventDefault();
					this.previousVideo();
					break;
				case 'ArrowDown':
					e.preventDefault();
					this.nextVideo();
					break;
				case ' ':
					e.preventDefault();
					this.playPauseVideo(this.currentVideoIndex);
					break;
			}
		},
		
		playVideo(index) {
			const video = this.$refs[`video-${index}`];
			if (video && video[0]) {
				video[0].play().then(() => {
					this.isPlaying = true;
				}).catch(err => {
					console.error('Error playing video:', err);
				});
			}
		},
		
		pauseVideo(index) {
			const video = this.$refs[`video-${index}`];
			if (video && video[0]) {
				video[0].pause();
				this.isPlaying = false;
			}
		},
		
		playPauseVideo(index) {
			const currentItem = this.videos[index];
			if (currentItem && currentItem.media_attachments[0].type === 'video') {
				const video = this.$refs[`video-${index}`];
				if (video && video[0]) {
					if (video[0].paused) {
						this.playVideo(index);
					} else {
						this.pauseVideo(index);
					}
				}
			}
			// For images, just set as current
			this.currentVideoIndex = index;
		},
		
		nextVideo() {
			if (this.currentVideoIndex < this.videos.length - 1) {
				this.goToVideo(this.currentVideoIndex + 1);
			} else {
				this.loadMoreVideos();
			}
		},
		
		previousVideo() {
			if (this.currentVideoIndex > 0) {
				this.goToVideo(this.currentVideoIndex - 1);
			}
		},
		
		goToVideo(index) {
			if (index >= 0 && index < this.videos.length) {
				// Pause current video
				this.pauseVideo(this.currentVideoIndex);
				
				// Update current index
				this.currentVideoIndex = index;
				
				// Play new video
				this.$nextTick(() => {
					this.playVideo(index);
					this.scrollToVideo(index);
				});
			}
		},
		
		scrollToVideo(index) {
			const wrapper = this.$refs.videosWrapper;
			if (wrapper) {
				const videoHeight = window.innerHeight;
				wrapper.scrollTo({
					top: index * videoHeight,
					behavior: 'smooth'
				});
			}
		},
		
		onVideoLoaded(index) {
			// Video metadata loaded
		},
		
		async toggleLike(video) {
			try {
				const response = await axios.post(`/api/pixelfed/v1/statuses/${video.id}/favourite`);
				video.favourited = !video.favourited;
				video.favourites_count += video.favourited ? 1 : -1;
			} catch (error) {
				console.error('Error toggling like:', error);
			}
		},
		
		async toggleBookmark(video) {
			try {
				const response = await axios.post(`/api/pixelfed/v1/statuses/${video.id}/bookmark`);
				video.bookmarked = !video.bookmarked;
			} catch (error) {
				console.error('Error toggling bookmark:', error);
			}
		},
		
		async openComments(video) {
			this.selectedVideo = video;
			this.showComments = true;
			this.loadingComments = true;
			
			try {
				const response = await axios.get(`/api/pixelfed/v1/statuses/${video.id}/context`);
				this.comments = response.data.descendants || [];
			} catch (error) {
				console.error('Error loading comments:', error);
				this.comments = [];
			} finally {
				this.loadingComments = false;
			}
		},
		
		closeComments() {
			this.showComments = false;
			this.comments = [];
			this.selectedVideo = null;
		},
		
		shareVideo(video) {
			if (navigator.share) {
				navigator.share({
					title: `Video by @${video.account.username}`,
					text: video.content || 'Check out this video on Bix!',
					url: video.url
				});
			} else {
				// Fallback: copy to clipboard
				navigator.clipboard.writeText(video.url).then(() => {
					alert('Video link copied to clipboard!');
				});
			}
		},
		
		async loadMoreVideos() {
			if (this.loading) return;
			
			this.loading = true;
			try {
				const response = await axios.get('/api/pixelfed/v1/timelines/videos', {
					params: {
						max_id: this.videos.length > 0 ? this.videos[this.videos.length - 1].id : null,
						limit: 10
					}
				});
				
				const newVideos = response.data.filter(item => 
					item.media_attachments && 
					item.media_attachments.length > 0 && 
					item.media_attachments[0].type === 'video'
				);
				
				this.videos.push(...newVideos);
			} catch (error) {
				console.error('Error loading more videos:', error);
			} finally {
				this.loading = false;
			}
		},
		
		formatCount(count) {
			if (count >= 1000000) {
				return (count / 1000000).toFixed(1) + 'M';
			} else if (count >= 1000) {
				return (count / 1000).toFixed(1) + 'K';
			}
			return count.toString();
		},
		
		formatDate(dateString) {
			const date = new Date(dateString);
			const now = new Date();
			const diff = now - date;
			
			const minutes = Math.floor(diff / 60000);
			const hours = Math.floor(diff / 3600000);
			const days = Math.floor(diff / 86400000);
			
			if (minutes < 60) {
				return `${minutes}m ago`;
			} else if (hours < 24) {
				return `${hours}h ago`;
			} else {
				return `${days}d ago`;
			}
		},
		
		handleAvatarError(e) {
			e.target.src = '/storage/avatars/default.png?v=2';
		}
	}
}
</script>

<style scoped>
.short-videos-container {
	position: fixed;
	top: 0;
	left: 0;
	width: 100vw;
	height: 100vh;
	background: #000;
	overflow: hidden;
	z-index: 1000;
}

.videos-wrapper {
	width: 100%;
	height: 100%;
	overflow-y: auto;
	scroll-snap-type: y mandatory;
	-webkit-overflow-scrolling: touch;
}

.video-item {
	position: relative;
	width: 100%;
	height: 100vh;
	scroll-snap-align: start;
	display: flex;
	align-items: center;
	justify-content: center;
	cursor: pointer;
}

.video-player, .image-player {
	width: 100%;
	height: 100%;
	object-fit: cover;
}

.video-overlay {
	position: absolute;
	bottom: 0;
	left: 0;
	right: 0;
	background: linear-gradient(transparent, rgba(0,0,0,0.7));
	padding: 20px;
	color: white;
}

.user-info {
	display: flex;
	align-items: center;
	margin-bottom: 15px;
}

.user-avatar {
	width: 50px;
	height: 50px;
	border-radius: 50%;
	margin-right: 15px;
	border: 2px solid white;
}

.user-details h4 {
	margin: 0;
	font-size: 16px;
	font-weight: bold;
}

.user-details p {
	margin: 0;
	font-size: 14px;
	opacity: 0.8;
}

.video-caption {
	margin-bottom: 20px;
	font-size: 14px;
	line-height: 1.4;
}

.interaction-buttons {
	position: absolute;
	right: 20px;
	bottom: 100px;
	display: flex;
	flex-direction: column;
	gap: 20px;
}

.interaction-item {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 5px;
}

.interaction-btn {
	width: 50px;
	height: 50px;
	border-radius: 50%;
	border: none;
	background: rgba(255,255,255,0.2);
	color: white;
	font-size: 20px;
	cursor: pointer;
	transition: all 0.3s ease;
	backdrop-filter: blur(10px);
}

.interaction-btn:hover {
	background: rgba(255,255,255,0.3);
	transform: scale(1.1);
}

.interaction-btn.liked {
	background: #e91e63;
	color: white;
}

.interaction-btn.bookmarked {
	background: #ffc107;
	color: white;
}

.interaction-count {
	font-size: 12px;
	font-weight: bold;
	text-align: center;
}

.play-indicator {
	position: absolute;
	top: 50%;
	left: 50%;
	transform: translate(-50%, -50%);
	width: 80px;
	height: 80px;
	border-radius: 50%;
	background: rgba(255,255,255,0.3);
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 30px;
	color: white;
	backdrop-filter: blur(10px);
}

.loading-indicator {
	position: absolute;
	top: 50%;
	left: 50%;
	transform: translate(-50%, -50%);
}

.spinner {
	width: 40px;
	height: 40px;
	border: 4px solid rgba(255,255,255,0.3);
	border-top: 4px solid white;
	border-radius: 50%;
	animation: spin 1s linear infinite;
}

@keyframes spin {
	0% { transform: rotate(0deg); }
	100% { transform: rotate(360deg); }
}

.navigation-dots {
	position: fixed;
	right: 10px;
	top: 50%;
	transform: translateY(-50%);
	display: flex;
	flex-direction: column;
	gap: 10px;
	z-index: 1001;
}

.nav-dot {
	width: 8px;
	height: 8px;
	border-radius: 50%;
	background: rgba(255,255,255,0.5);
	cursor: pointer;
	transition: all 0.3s ease;
}

.nav-dot.active {
	background: white;
	transform: scale(1.5);
}

.comments-modal {
	position: fixed;
	top: 0;
	left: 0;
	width: 100vw;
	height: 100vh;
	background: rgba(0,0,0,0.8);
	display: flex;
	align-items: center;
	justify-content: center;
	z-index: 2000;
}

.comments-content {
	background: white;
	border-radius: 15px;
	width: 90%;
	max-width: 500px;
	max-height: 80vh;
	overflow: hidden;
	display: flex;
	flex-direction: column;
}

.comments-header {
	padding: 20px;
	border-bottom: 1px solid #eee;
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.comments-header h3 {
	margin: 0;
	font-size: 18px;
}

.close-btn {
	background: none;
	border: none;
	font-size: 20px;
	cursor: pointer;
	color: #666;
}

.comments-list {
	flex: 1;
	overflow-y: auto;
	padding: 20px;
}

.loading-comments {
	display: flex;
	justify-content: center;
	padding: 40px;
}

.no-comments {
	text-align: center;
	padding: 40px;
	color: #666;
}

.comment-item {
	display: flex;
	gap: 15px;
	margin-bottom: 20px;
}

.comment-avatar {
	width: 40px;
	height: 40px;
	border-radius: 50%;
	flex-shrink: 0;
}

.comment-content {
	flex: 1;
}

.comment-content strong {
	font-size: 14px;
	color: #333;
}

.comment-content p {
	margin: 5px 0;
	font-size: 14px;
	line-height: 1.4;
}

.comment-content small {
	color: #666;
	font-size: 12px;
}

/* Mobile Responsive */
@media (max-width: 768px) {
	.interaction-buttons {
		right: 10px;
		bottom: 80px;
		gap: 15px;
	}
	
	.interaction-btn {
		width: 45px;
		height: 45px;
		font-size: 18px;
	}
	
	.video-overlay {
		padding: 15px;
	}
	
	.user-avatar {
		width: 40px;
		height: 40px;
	}
	
	.comments-content {
		width: 95%;
		max-height: 90vh;
	}
}

/* Loading and Empty States */
.loading-state, .empty-state {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	height: 100vh;
	text-align: center;
	color: #666;
}

.loading-state .spinner {
	width: 40px;
	height: 40px;
	border: 4px solid #f3f3f3;
	border-top: 4px solid #007bff;
	border-radius: 50%;
	animation: spin 1s linear infinite;
	margin-bottom: 20px;
}

@keyframes spin {
	0% { transform: rotate(0deg); }
	100% { transform: rotate(360deg); }
}

.empty-state i {
	color: #ccc;
	margin-bottom: 20px;
}

.empty-state h3 {
	margin-bottom: 10px;
	color: #333;
}

.empty-state p {
	color: #666;
	font-size: 14px;
}
</style>