window.RevealBackgroundAudio = window.RevealBackgroundAudio || {
    id: 'RevealBackgroundAudio',
    playing: false,
    backup_config: {},
    deck: null,
    // If not null, it will either an Audio element or WaveSurfer instance.
    current_audio: null,
    configs_to_control: {autoSlide: 1, controls: false, keyboard: false},
    // If true, the audio will be paused during the transition between slides
    pause_during_transition: false,
    audio_source : null,
    audio_guide_id: null,
    init: function(deck) {
        this.deck = deck;
        let reveal_element = deck.getRevealElement();
        let plugin = this;
        let config = deck.getConfig();
        if ('background_audio' in config) {
            plugin.pause_during_transition = config.background_audio.pause_during_transition;
            plugin.audio_source = config.background_audio.source;
            if (config.background_audio.audio_guide_id) {
                plugin.audio_guide_id = config.background_audio.audio_guide_id;
            }
        }

        // Get all start buttons
        const start_buttons = reveal_element.querySelectorAll('[data-bg-audio-start-button]');

        // Loop through the buttons and attach click event listener
        start_buttons.forEach(child => {
            child.classList.add('background-audio-start-button');
            child.addEventListener('click', function (event){
                // Prevent the default action of the button so, it wont cuase problmes
                // in the preview mdde where presentation will be embedded within a form.
                event.preventDefault();
                plugin.startAudio(this);
            });
        });
        deck.on('slidechanged', (event) => {
            if (plugin.current_audio && plugin.pause_during_transition) {
                plugin.current_audio.pause();
            }
        });
        deck.on('slidetransitionend', (event) => {
            if (plugin.current_audio && plugin.pause_during_transition) {
                plugin.current_audio.play();
            }
        });
        deck.on('autoslideresumed', (event) => {
            if (plugin.current_audio) {
                plugin.current_audio.play();
            }
        });
        deck.on('autoslidepaused', (event) => {
            if (plugin.current_audio) {
                plugin.current_audio.pause();
            }
        });
    },
    controlConfigs: function () {
        let config = this.deck.getConfig();
        this.backupConfigs(Object.keys(this.configs_to_control), config);
        this.deck.configure(this.configs_to_control);
    },
    backupConfigs: function(configs_items) {
        const plugin = this;
        let config = plugin.deck.getConfig();
        configs_items.forEach((property, index) => {
            plugin.backup_config[property] = config[property];
        });
    },
    restoreConfigs: function(configs_items) {
        const plugin = this;
        let config = {};
        configs_items.forEach((property, index) => {
            config[property] = plugin.backup_config[property];
        });
        plugin.deck.configure(config);
    },
    startAudio: function(button) {
        const plugin = this;
        let config = plugin.deck.getConfig();
        let audio_source;
        let audio_guide
        if (plugin.audio_guide_id) {
            audio_guide = document.getElementById(plugin.audio_guide_id);
        }
        if (button.hasAttribute('data-bg-audio-src')) {
            audio_source = button.getAttribute('data-bg-audio-src');
        }
        else {
            audio_source = config.background_audio.source
        }
        if (audio_guide && audio_guide.wavesurfer) {
            // We can only play presentation in normal speed thus audio also needs to be played in normal speed.
            audio_guide.wavesurfer.setPlaybackRate(1);

            audio_guide.wavesurfer.play();
            plugin.controlConfigs();
            plugin.current_audio = audio_guide.wavesurfer;
            audio_guide.wavesurfer.on('finish', function () {
                plugin.stopAudio();
            });
        }
        else if (audio_source) {
            let audio = new Audio(audio_source);
            if (audio) {
                audio.addEventListener("loadeddata", (event) => {
                    // Start playing once the media is ready.
                    // Otherwise it was having a clipping at the beginning of the audio playing.
                    audio.play();
                    plugin.controlConfigs();
                });
                plugin.current_audio = audio;
                audio.addEventListener("ended", (event) => {
                    plugin.stopAudio()
                });
            }
        }
    },
    stopAudio: function() {
        this.current_audio = null;
        this.restoreConfigs(Object.keys(this.configs_to_control));
    }

};