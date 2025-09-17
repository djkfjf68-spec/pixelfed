<template>
    <div class="short-videos-container">
        <div class="video-feed" ref="videoFeed">
            <div 
                v-for="(video, index) in videos" 
                :key="video.id"
                class="video-item"
                :class="{ 'active': currentVideoIndex === index }"
                @click="playVideo(index)"
            >
                <div class="video-wrapper">
                    <video 
                        :ref="`video-${index}`"
                        :src="video.media_attachments[0].url"
                        :poster="video.media_attachments[0].preview_url"
                        class="video-player"
                        loop
                        muted
                        playsinline
                        @ended="nextVideo"
                    ></video>
                    
                    <div class="video-overlay">
                        <div class="video-info">
                            <div class="user-info">
                                <img :src="video.account.avatar" class="user-avatar" />
                                <span class="username">{{ video.account.username }}</span>
                            </div>
                            <p class="video-caption" v-if="video.content_text">{{ video.content_text }}</p>
                        </div>
                        
                        <div class="video-actions">
                            <div class="action-item" @click.stop="toggleLike(video)">
                                <i class="fas fa-heart" :class="{ 'liked': video.favourited }"></i>
                                <span class="count">{{ formatCount(video.favourites_count) }}</span>
                            </div>
                            
                            <div class="action-item" @click.stop="openComments(video)">
                                <i class="fas fa-comment"></i>
                                <span class="count">{{ formatCount(video.replies_count) }}</span>
                            </div>
                            
                            <div class="action-item" @click.stop="shareVideo(video)">
                                <i class="fas fa-share"></i>
                                <span class="count">{{ formatCount(video.reblogs_count) }}</span>
                            </div>
                            
                            <div class="action-item" @click.stop="toggleBookmark(video)">
                                <i class="fas fa-bookmark" :class="{ 'bookmarked': video.bookmarked }"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="loading" v-if="loading">
            <div class="spinner"></div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'ShortVideos',
    
    data() {
        return {
            videos: [],
            currentVideoIndex: 0,
            loading: false,
            page: 1,
            hasMore: true
        }
    },
    
    mounted() {
        this.loadVideos();
        this.setupScrollListener();
        this.setupKeyboardListener();
    },
    
    beforeDestroy() {
        this.removeScrollListener();
        this.removeKeyboardListener();
    },
    
    methods: {
        async loadVideos() {
            if (this.loading || !this.hasMore) return;
            
            this.loading = true;
            
            try {
                const response = await axios.get(`/api/pixelfed/v1/timelines/videos?page=${this.page}`);
                const newVideos = response.data;
                
                if (newVideos.length === 0) {
                    this.hasMore = false;
                } else {
                    this.videos.push(...newVideos);
                    this.page++;
                }
            } catch (error) {
                console.error('Error loading videos:', error);
            } finally {
                this.loading = false;
            }
        },
        
        playVideo(index) {
            // Pause current video
            if (this.currentVideoIndex !== index) {
                const currentVideo = this.$refs[`video-${this.currentVideoIndex}`];
                if (currentVideo && currentVideo[0]) {
                    currentVideo[0].pause();
                }
            }
            
            // Play new video
            this.currentVideoIndex = index;
            const newVideo = this.$refs[`video-${index}`];
            if (newVideo && newVideo[0]) {
                newVideo[0].play();
            }
            
            // Scroll to video
            this.scrollToVideo(index);
        },
        
        nextVideo() {
            if (this.currentVideoIndex < this.videos.length - 1) {
                this.playVideo(this.currentVideoIndex + 1);
            } else if (this.hasMore) {
                this.loadVideos().then(() => {
                    if (this.videos.length > this.currentVideoIndex + 1) {
                        this.playVideo(this.currentVideoIndex + 1);
                    }
                });
            }
        },
        
        previousVideo() {
            if (this.currentVideoIndex > 0) {
                this.playVideo(this.currentVideoIndex - 1);
            }
        },
        
        scrollToVideo(index) {
            const videoElement = this.$refs.videoFeed.children[index];
            if (videoElement) {
                videoElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        },
        
        setupScrollListener() {
            this.scrollHandler = this.throttle(() => {
                const container = this.$refs.videoFeed;
                if (container.scrollTop + container.clientHeight >= container.scrollHeight - 1000) {
                    this.loadVideos();
                }
            }, 200);
            
            this.$refs.videoFeed.addEventListener('scroll', this.scrollHandler);
        },
        
        removeScrollListener() {
            if (this.scrollHandler) {
                this.$refs.videoFeed.removeEventListener('scroll', this.scrollHandler);
            }
        },
        
        setupKeyboardListener() {
            this.keyHandler = (e) => {
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
                        this.togglePlayPause();
                        break;
                }
            };
            
            document.addEventListener('keydown', this.keyHandler);
        },
        
        removeKeyboardListener() {
            if (this.keyHandler) {
                document.removeEventListener('keydown', this.keyHandler);
            }
        },
        
        togglePlayPause() {
            const video = this.$refs[`video-${this.currentVideoIndex}`];
            if (video && video[0]) {
                if (video[0].paused) {
                    video[0].play();
                } else {
                    video[0].pause();
                }
            }
        },
        
        async toggleLike(video) {
            try {
                if (video.favourited) {
                    await axios.post(`/api/pixelfed/v1/statuses/${video.id}/unfavourite`);
                    video.favourited = false;
                    video.favourites_count--;
                } else {
                    await axios.post(`/api/pixelfed/v1/statuses/${video.id}/favourite`);
                    video.favourited = true;
                    video.favourites_count++;
                }
            } catch (error) {
                console.error('Error toggling like:', error);
            }
        },
        
        async toggleBookmark(video) {
            try {
                if (video.bookmarked) {
                    await axios.post(`/api/pixelfed/v1/statuses/${video.id}/unbookmark`);
                    video.bookmarked = false;
                } else {
                    await axios.post(`/api/pixelfed/v1/statuses/${video.id}/bookmark`);
                    video.bookmarked = true;
                }
            } catch (error) {
                console.error('Error toggling bookmark:', error);
            }
        },
        
        openComments(video) {
            // Open comments modal or navigate to post
            window.location.href = `/p/${video.account.username}/${video.id}`;
        },
        
        shareVideo(video) {
            if (navigator.share) {
                navigator.share({
                    title: `Video by ${video.account.username}`,
                    url: video.url
                });
            } else {
                // Fallback: copy to clipboard
                navigator.clipboard.writeText(video.url);
                alert('Link copied to clipboard!');
            }
        },
        
        formatCount(count) {
            if (count < 1000) return count.toString();
            if (count < 1000000) return (count / 1000).toFixed(1) + 'K';
            return (count / 1000000).toFixed(1) + 'M';
        },
        
        throttle(func, delay) {
            let timeoutId;
            let lastExecTime = 0;
            return function (...args) {
                const currentTime = Date.now();
                
                if (currentTime - lastExecTime > delay) {
                    func.apply(this, args);
                    lastExecTime = currentTime;
                } else {
                    clearTimeout(timeoutId);
                    timeoutId = setTimeout(() => {
                        func.apply(this, args);
                        lastExecTime = Date.now();
                    }, delay - (currentTime - lastExecTime));
                }
            };
        }
    }
}
</script>

<style scoped>
.short-videos-container {
    height: 100vh;
    overflow: hidden;
    background: #000;
    position: relative;
}

.video-feed {
    height: 100%;
    overflow-y: auto;
    scroll-snap-type: y mandatory;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.video-feed::-webkit-scrollbar {
    display: none;
}

.video-item {
    height: 100vh;
    width: 100%;
    position: relative;
    scroll-snap-align: start;
    display: flex;
    align-items: center;
    justify-content: center;
}

.video-wrapper {
    position: relative;
    width: 100%;
    height: 100%;
    max-width: 400px;
    background: #000;
}

.video-player {
    width: 100%;
    height: 100%;
    object-fit: cover;
    cursor: pointer;
}

.video-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(transparent, rgba(0,0,0,0.7));
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
}

.video-info {
    flex: 1;
    color: white;
}

.user-info {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
}

.user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    margin-right: 10px;
}

.username {
    font-weight: bold;
    font-size: 14px;
}

.video-caption {
    font-size: 14px;
    line-height: 1.4;
    margin: 0;
    max-width: 250px;
}

.video-actions {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 20px;
    margin-left: 20px;
}

.action-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    cursor: pointer;
    color: white;
    transition: transform 0.2s;
}

.action-item:hover {
    transform: scale(1.1);
}

.action-item i {
    font-size: 24px;
    margin-bottom: 5px;
}

.action-item i.liked {
    color: #ff3040;
}

.action-item i.bookmarked {
    color: #ffc107;
}

.count {
    font-size: 12px;
    font-weight: bold;
}

.loading {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    color: white;
}

.spinner {
    width: 20px;
    height: 20px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top: 2px solid white;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Mobile responsive */
@media (max-width: 768px) {
    .video-wrapper {
        max-width: 100%;
    }
    
    .video-overlay {
        padding: 15px;
    }
    
    .video-actions {
        gap: 15px;
        margin-left: 15px;
    }
    
    .action-item i {
        font-size: 20px;
    }
    
    .video-caption {
        max-width: 200px;
        font-size: 13px;
    }
}
</style>