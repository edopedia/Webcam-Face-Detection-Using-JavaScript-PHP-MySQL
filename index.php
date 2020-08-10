<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="chrome=1">
		<title>Webcam Face Detection Using JavaScript, PHP, and MySQL - Edopedia.com</title>
		
		<!-- Add these JavaScript files from pico.js library -->
		<script src="picojs/examples/camvas.js"></script>
		<script src="picojs/pico.js"></script>
		<script src="picojs/lploc.js"></script>
		
		<script>
			var initialized = false;
			function button_callback() {
				/*
					(0) check whether we're already running face detection
				*/
				if(initialized)
					return; // if yes, then do not initialize everything again
				/*
					(1) initialize the pico.js face detector
				*/
				var update_memory = pico.instantiate_detection_memory(1); // we will use the detecions of the last 1 frame
				var facefinder_classify_region = function(r, c, s, pixels, ldim) {return -1.0;};
				var cascadeurl = 'https://raw.githubusercontent.com/nenadmarkus/pico/c2e81f9d23cc11d1a612fd21e4f9de0921a5d0d9/rnt/cascades/facefinder';
				fetch(cascadeurl).then(function(response) {
					response.arrayBuffer().then(function(buffer) {
						var bytes = new Int8Array(buffer);
						facefinder_classify_region = pico.unpack_cascade(bytes);
						console.log('* facefinder loaded');
					})
				})
				/*
					(2) initialize the lploc.js library with a pupil localizer
				*/
				var do_puploc = function(r, c, s, nperturbs, pixels, nrows, ncols, ldim) {return [-1.0, -1.0];};
				//var puplocurl = '../puploc.bin';
				var puplocurl = 'https://f002.backblazeb2.com/file/tehnokv-www/posts/puploc-with-trees/demo/puploc.bin'
				fetch(puplocurl).then(function(response) {
					response.arrayBuffer().then(function(buffer) {
						var bytes = new Int8Array(buffer);
						do_puploc = lploc.unpack_localizer(bytes);
						console.log('* puploc loaded');
					})
				})
				/*
					(3) get the drawing context on the canvas and define a function to transform an RGBA image to grayscale
				*/
				var ctx = document.getElementsByTagName('canvas')[0].getContext('2d');
				function rgba_to_grayscale(rgba, nrows, ncols) {
					var gray = new Uint8Array(nrows*ncols);
					for(var r=0; r<nrows; ++r)
						for(var c=0; c<ncols; ++c)
							// gray = 0.2*red + 0.7*green + 0.1*blue
							gray[r*ncols + c] = (2*rgba[r*4*ncols+4*c+0]+7*rgba[r*4*ncols+4*c+1]+1*rgba[r*4*ncols+4*c+2])/10;
					return gray;
				}
				/*
					(4) this function is called each time a video frame becomes available
				*/
				var processfn = function(video, dt) {
					// render the video frame to the canvas element and extract RGBA pixel data
					ctx.drawImage(video, 0, 0);
					var rgba = ctx.getImageData(0, 0, 640, 480).data;
					// prepare input to `run_cascade`
					image = {
						"pixels": rgba_to_grayscale(rgba, 480, 640),
						"nrows": 480,
						"ncols": 640,
						"ldim": 640
					}
					params = {
						"shiftfactor": 0.1, // move the detection window by 10% of its size
						"minsize": 100,     // minimum size of a face
						"maxsize": 1000,    // maximum size of a face
						"scalefactor": 1.1  // for multiscale processing: resize the detection window by 10% when moving to the higher scale
					}
					// run the cascade over the frame and cluster the obtained detections
					// dets is an array that contains (r, c, s, q) quadruplets
					// (representing row, column, scale and detection score)
					dets = pico.run_cascade(image, facefinder_classify_region, params);
					dets = update_memory(dets);
					dets = pico.cluster_detections(dets, 0.2); // set IoU threshold to 0.2
					// draw detections
					for(i=0; i<dets.length; ++i)
					{
						// check the detection score
						// if it's above the threshold, draw it
						// (the constant 50.0 is empirical: other cascades might require a different one)
						if(dets[i][3]>50.0)
						{
							var r, c, s;
							//
							ctx.beginPath();
							ctx.arc(dets[i][1], dets[i][0], dets[i][2]/2, 0, 2*Math.PI, false);
							ctx.lineWidth = 3;
							ctx.strokeStyle = 'red';
							ctx.stroke();
							//
							// find the eye pupils for each detected face
							// starting regions for localization are initialized based on the face bounding box
							// (parameters are set empirically)
							// first eye
							r = dets[i][0] - 0.075*dets[i][2];
							c = dets[i][1] - 0.175*dets[i][2];
							s = 0.35*dets[i][2];
							[r, c] = do_puploc(r, c, s, 63, image)
							if(r>=0 && c>=0)
							{
								ctx.beginPath();
								ctx.arc(c, r, 1, 0, 2*Math.PI, false);
								ctx.lineWidth = 3;
								ctx.strokeStyle = 'red';
								ctx.stroke();
							}
							// second eye
							r = dets[i][0] - 0.075*dets[i][2];
							c = dets[i][1] + 0.175*dets[i][2];
							s = 0.35*dets[i][2];
							[r, c] = do_puploc(r, c, s, 63, image)
							if(r>=0 && c>=0)
							{
								ctx.beginPath();
								ctx.arc(c, r, 1, 0, 2*Math.PI, false);
								ctx.lineWidth = 3;
								ctx.strokeStyle = 'red';
								ctx.stroke();
							}
							
							// At this point, we already know that the human face is detected in webcam. So, We'll simply create an image from canvas that is displaying the webcam result in real-time.
							var can = document.getElementsByTagName('canvas')[0]
							var img = new Image();
							img.src = can.toDataURL('image/jpeg', 1.0);
							
							// Now, we will send the image to server and process it using PHP. Also, we have to save its path in MySQL database for later use.
							var data = JSON.stringify({ image: img.src });
							fetch("save.php",
							{
								method: "POST",
								body: data
							})
							.then(function(res){ return res.json(); })
							.then(function(data){ return alert( data.message ); })
							
							// This alert statement is a little hack to temporarily stop the execution of script.
							alert('Face found!');
						}
					}
				}
				/*
					(5) instantiate camera handling (see https://github.com/cbrandolino/camvas)
				*/
				var mycamvas = new camvas(ctx, processfn);
				/*
					(6) it seems that everything went well
				*/
				initialized = true;
			}
		</script>
	</head>
	
	<body>
		<div>
			<h3>Webcam Face Detection Using JavaScript, PHP, and MySQL by Edopedia.com</h3>
			<p>Click the "Start Webcam" button below and allow the page to access your webcam.</p>
			
			<p>View Tutorial: <a href="https://www.edopedia.com/blog/webcam-face-detection-javascript-php-mysql/" target="_blank">https://www.edopedia.com/blog/webcam-face-detection-javascript-php-mysql/</a></p>
		</div>
		<hr />
		<p>
			<center>
				<input type="button" value="Start Webcam" onclick="button_callback()">
				&nbsp;
				<a href="view.php" target="_blank">View Saved Images</a>
			</center>
		</p>
		<hr />
		<p>
			<center>
				<canvas width="640" height="480"></canvas>
			</center>
		</p>
	</body>
</html>