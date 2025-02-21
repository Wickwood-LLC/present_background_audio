(function(Drupal){

  /**
   * Save updated regions data back to the input field for form submissions.
   *
   * @param {*} regions
   * @param {*} regions_data
   * @param {*} input_element
   */
  function updateInputField(regions, regions_data, input_element) {
    let regions_data_copy = {...regions_data}
    regions.forEach((region, index) => {
      regions_data_copy[index].start = region.start;
      regions_data_copy[index].end = region.end;
    });
    input_element.value = JSON.stringify(regions_data_copy);
  }

  Drupal.behaviors.audio_guide = {
    attach: function(context, settings) {
      once('wavesurfer', '.audio-guide-track').forEach(function (element) {
        const wrapper = element.parentElement;

        // Get the default play mode.
        let play_mode = wrapper.querySelector('input[name="audio_guide[play_mode]"]:checked').value;
        const play_mode_radios = wrapper.querySelectorAll('input[name="audio_guide[play_mode]"]');
        play_mode_radios.forEach(radio => {
          radio.addEventListener('click', () => {
            // Set as the active play mode.
            play_mode = radio.value;
          });
        });

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

        // Play on click
        ws.on('interaction', () => {
          ws.play()
        });

        // Give regions a random color when they are created
        const random = (min, max) => Math.random() * (max - min) + min
        const randomColor = () => `rgba(${random(0, 255)}, ${random(0, 255)}, ${random(0, 255)}, 0.5)`


        const hidden_input = wrapper.querySelector('input[type="hidden"]');
        // Get the region configs from the hidden input.
        let region_configs = JSON.parse(hidden_input.value);
        // List to keep regions in order.
        let regions = [];
        ws.on('decode', function (duration) {
          region_configs.forEach((region_config) => {
            // Set a random color if none is set.
            if (!('color' in region_config)) {
              region_config.color = randomColor();
            }
            let region = regions_plugin.addRegion(region_config);
            region.fixed_size = region_config.fixed_size;
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
        });

        ws.on('error', e => alert(e))

        regions_plugin.on('region-clicked', (region, e) => {
          if (play_mode === 'region') {
            e.stopPropagation() // prevent triggering a click on the waveform
            region.play(true)
          }
        })

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
              }
            }
          }
          else if (side === 'end') {
            if (region.next_region) {
              upper_limit = region.next_region.end - ( region.next_region.fixed_size ? region.next_region.fixed_size : common_region_min_width);
              if (region.end > upper_limit) {
                region.setOptions({end: upper_limit});
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
              }
            }
            if (region.next_region) {
              // Don't allow this go beyond making the next region less than minimum width.
              upper_limit = region.next_region.end - ( region.next_region.fixed_size ? region.next_region.fixed_size : common_region_min_width);
              if (region.end > upper_limit) {
                region.setOptions({end: upper_limit});
              }
            }
          }
        }

        /**
         * Keep watching while regions are resized and dragged.
         */
        regions_plugin.on('region-update', (region, side) => {
          // First ensure that this region not collapsing.
          preventRegionCollapsing(region, side);
          // Then ensure it does not go beyond previous and next regions.
          preventOverlapping(region, side);
          let limit;
          if (region.fixed_size) {
            // This is fixed sized region like transitions.
            if (side === 'start') {
              if (region.next_region) {
                // Don't allow to crush next region.
                limit = region.next_region.end - common_region_min_width - region.fixed_size;
                if (region.start > limit) {
                  region.setOptions({start: limit});
                }
              }
              region.setOptions({end: region.start + region.fixed_size});
            }
            if (side === 'end') {
              if (region.previous_region) {
                // Don't allow to crush previous region.
                limit = region.previous_region.start + common_region_min_width + region.fixed_size;
                if (region.end < limit) {
                  region.setOptions({end: limit});
                }
              }
              region.setOptions({start: region.end - region.fixed_size});
            }
          }
          if (region.previous_region) {
            updatePreviousRegion(region);
          }
          if (region.next_region) {
            region.next_region.setOptions({start: region.end});
            updateNextRegion(region);
          }
        });

        regions_plugin.on('region-updated', (region, side) => {
          updateInputField(regions, region_configs, hidden_input);
        });
      });
    }
  }
})(Drupal, WaveSurfer, once);