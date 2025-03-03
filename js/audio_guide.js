(function(Drupal){

  /**
   * Save updated regions data back to the input field for form submissions.
   *
   * @param {*} regions
   * @param {*} regions_data
   * @param {*} input_element
   */
  function updateInputField(regions, regions_data, input_element) {
    let regions_data_copy = regions_data.slice();``
    regions.forEach((region, index) => {
      regions_data_copy[index].start = Math.round(region.start * 1000);
      regions_data_copy[index].end = Math.round(region.end * 1000);
    });
    input_element.value = JSON.stringify(regions_data_copy);
  }

  Drupal.behaviors.audio_guide = {
    attach: function(context, settings) {
      once('wavesurfer', '.audio-guide-track').forEach(function (element) {
        const wrapper = element.parentElement;

        const common_region_min_width = 0.1;
        let config = JSON.parse(element.getAttribute('data-configs'));
        config.container = element;
        // Initialize the Regions plugin
        const regions_plugin = WaveSurfer.Regions.create();
        const timeline = WaveSurfer.Timeline.create();
        const zoom_plugin = WaveSurfer.Zoom.create({
          // the amount of zoom per wheel step, e.g. 0.5 means a 50% magnification per scroll
          scale: 0.5,
          // Optionally, specify the maximum pixels-per-second factor while zooming
          maxZoom: 500,
        });
        const hover_plugin = WaveSurfer.Hover.create({
          lineColor: '#ff0000',
          lineWidth: 2,
          labelBackground: '#555',
          labelColor: '#fff',
          labelSize: '11px',
          formatTimeCallback(seconds) {
            const minutes = Math.floor(seconds / 60)
            const secondsRemainder = Math.floor(seconds) % 60
            const paddedSeconds = `0${secondsRemainder}`.slice(-2)
            const milli_seonds = seconds % 1
            return `${minutes}:${paddedSeconds}.${milli_seonds.toFixed(3).slice(2)}`
          },
        });
        // Add the plugin to the WaveSurfer instance
        config.plugins = [
          regions_plugin,
          timeline,
          zoom_plugin,
          hover_plugin,
        ];
        // Create a WaveSurfer instance
        const ws = WaveSurfer.create(config);
        let active_region = null;

        // Play on click
        ws.on('interaction', () => {
          // ws.play()
        });

        // Give regions a random color when they are created
        const random = (min, max) => Math.random() * (max - min) + min
        const randomColor = () => `rgba(${random(0, 255)}, ${random(0, 255)}, ${random(0, 255)}, 0.5)`


        const regions_data_store = wrapper.querySelector('[class~="regions-data-store"]');
        // Get the region configs from the hidden input.
        let region_configs = JSON.parse(regions_data_store.value);
        // List to keep regions in order.
        let regions = [];
        ws.on('decode', function (duration) {
          element.wavesurfer = ws;
          region_configs.forEach((region_config) => {
            // Set a random color if none is set.
            if (!('color' in region_config)) {
              region_config.color = randomColor();
            }
            // Create a copy of the region config.
            // Otherwise the content we set with HTMLElement will be sent back to server
            // and cause an error in processing data.
            let region_config_copy = {...region_config};
            let label = document.createElement('strong');
            label.innerHTML = region_config.content;
            region_config_copy.content = label;
            region_config_copy.start = region_config.start / 1000;
            region_config_copy.end = region_config.end / 1000;
            let region = regions_plugin.addRegion(region_config_copy);
            region.fixed_size = region_config.fixed_size / 1000;
            region.type = region_config.type;
            regions.push(region);
          });

          // Link regions to each other.
          // So that we can identify the previous and next regions easily.
          let previous_region = null;
          regions.forEach((region, index) => {
            if (previous_region) {
              previous_region.next_region = region;
              region.previous_region = previous_region;
            }
            previous_region = region;
          });
          // Set the first region as active by default.
          if (regions.length > 0) {
            active_region = regions[0];
          }
        });

        ws.on('error', e => alert(e))

        regions_plugin.on('region-clicked', (region, e) => {
          active_region = region;
          if (ws.isPlaying()) {
            ws.pause();
          }
        })
        regions_plugin.on('region-in', (region, e) => {
          active_region = region;
        });

        function updatePreviousRegion(region) {
          if (region.previous_region) {
            region.previous_region.setOptions({end: region.start});
            if (region.previous_region.fixed_size) {
              region.previous_region.setOptions({start: region.start - region.previous_region.fixed_size});
            }
            preventRegionCollapsing(region.previous_region, 'end');
            preventOverlapping(region.previous_region, 'end');
            updatePreviousRegion(region.previous_region);
          }
        }

        function updateNextRegion(region) {
          if (region.next_region) {
            region.next_region.setOptions({start: region.end});
            if (region.next_region.fixed_size) {
              region.next_region.setOptions({end: region.end + region.next_region.fixed_size});
            }
            preventRegionCollapsing(region.next_region, 'start');
            preventOverlapping(region.next_region, 'start');
            updateNextRegion(region.next_region);
          }
        }

        /**
         * Ensure the region not becomming less than minimum width.
         *
         * @param {*} region
         * @param {*} side
         */
        function preventRegionCollapsing(region, side) {
          let limit;
          let current_region_min_width = region.fixed_size ? region.fixed_size : common_region_min_width;
          if (side === 'start') {
            limit = region.end - current_region_min_width;
            if (region.start > limit) {
              region.setOptions({start: limit});
            }
          }
          else if (side === 'end') {
            limit = region.start + current_region_min_width;
            if (region.end < limit) {
              region.setOptions({end: limit});
            }
          }
        }

        /**
         * Ensure the region go beyon bounderies of previous and next regions.
         *
         * @param {*} region
         * @param {*} side
         */
        function preventOverlapping(region, side) {
          let lower_limit, upper_limit;
          if (side === 'start') {
            if (region.previous_region) {
              lower_limit = region.previous_region.start + (region.previous_region.fixed_size ? region.previous_region.fixed_size :common_region_min_width);
              if (region.start < lower_limit) {
                region.setOptions({start: lower_limit});
                region.previous_region.setOptions({end: region.start});
              }
            }
            if (region.next_region) {
              upper_limit = region.next_region.end - (region.next_region.fixed_size ? region.next_region.fixed_size : common_region_min_width);
              upper_limit -= region.fixed_size ? region.fixed_size : common_region_min_width;
              if (region.start > upper_limit) {
                region.setOptions({start: upper_limit});
                region.next_region.setOptions({start: region.end});
              }
            }
          }
          else if (side === 'end') {
            if (region.next_region) {
              upper_limit = region.next_region.end - ( region.next_region.fixed_size ? region.next_region.fixed_size : common_region_min_width);
              if (region.end > upper_limit) {
                region.setOptions({end: upper_limit});
                region.next_region.setOptions({start: region.end});
              }
            }
            if (region.previous_region) {
              lower_limit = region.previous_region.start;
              lower_limit += region.previous_region.fixed_size ? region.previous_region.fixed_size : common_region_min_width;
              lower_limit += region.fixed_size ? region.fixed_size : common_region_min_width;
              if (region.end < lower_limit) {
                region.setOptions({end: lower_limit});
                region.previous_region.setOptions({end: region.start});
              }
            }
          }
          else {
            // Dragging
            if (region.previous_region) {
              // Don't allow this go beyond making the previous region less than minimum width.
              lower_limit = region.previous_region.start + ( region.previous_region.fixed_size ? region.previous_region.fixed_size : common_region_min_width);
              if (region.start < lower_limit) {
                region.setOptions({start: lower_limit});
                region.previous_region.setOptions({end: region.start});
              }
            }
            if (region.next_region) {
              // Don't allow this go beyond making the next region less than minimum width.
              upper_limit = region.next_region.end - ( region.next_region.fixed_size ? region.next_region.fixed_size : common_region_min_width);
              if (region.end > upper_limit) {
                region.setOptions({end: upper_limit});
                region.next_region.setOptions({start: region.end});
              }
            }
          }
        }

        /**
         * Keep watching while regions are resized and dragged.
         */
        regions_plugin.on('region-update', (region, side) => {
          // First ensure that this region not collapsing when resizing.
          preventRegionCollapsing(region, side);
          // Then ensure it does not go beyond previous and next regions.
          preventOverlapping(region, side);
          if (region.fixed_size) {
            // This is fixed sized region like transitions.
            if (side === 'start') {
              region.setOptions({end: region.start + region.fixed_size});
            }
            if (side === 'end') {
              region.setOptions({start: region.end - region.fixed_size});
            }
          }
          if (region.previous_region) {
            // region.previous_region.setOptions({end: region.start});
            updatePreviousRegion(region);
          }
          if (region.next_region) {
            // region.next_region.setOptions({start: region.end});
            updateNextRegion(region);
          }
        });

        regions_plugin.on('region-updated', (region, side) => {
          updateInputField(regions, region_configs, regions_data_store);
        });
        let play_button = wrapper.querySelector('.audio-controls .audio-play');
        play_button.addEventListener('click', (event) => {
          event.preventDefault();
          ws.playPause();
        });

        let play_from_start_button = wrapper.querySelector('.audio-controls .audio-goto-start');
        play_from_start_button.addEventListener('click', (event) => {
          event.preventDefault();
          ws.setTime(0);
        });

        let play_step_back = wrapper.querySelector('.audio-controls .audio-goto-step-back');
        play_step_back.addEventListener('click', (event) => {
          event.preventDefault();
          if (active_region) {
            let current_region = active_region;
            let previous_region;
            while (previous_region = current_region.previous_region) {
              current_region = previous_region;
              if (current_region.type && current_region.type === 'transition') {
                break;
              }
            }
            ws.setTime(current_region.start);
          }
        });

        /** When the audio starts playing */
        ws.on('play', () => {
          play_button.classList.add('playing');
        })

        /** When the audio pauses */
        ws.on('pause', () => {
          play_button.classList.remove('playing');
        })

        /** When the audio finishes playing */
        ws.on('finish', () => {
          play_button.classList.remove('playing');
        })

        let play_rate_input = wrapper.querySelector('.audio-play-rate');
        let play_rate_value = wrapper.querySelector('.audio-play-rate-value');
        play_rate_input.addEventListener('change', (event) => {
          ws.setPlaybackRate(event.target.value);
          play_rate_value.innerHTML = `${event.target.value}x`;
        });
        element.addEventListener('playback_rate', (event) => {
          play_rate_input.value = event.detail.playback_rate;
          play_rate_value.innerHTML = `${event.detail.playback_rate}x`;
        });
      });
    }
  }
})(Drupal, WaveSurfer, once);