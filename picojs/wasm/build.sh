#
#
wget https://github.com/nenadmarkus/pico/raw/c506795063f96ffeb4f9cce5d78a2c4b57fb88b0/rnt/picornt.c
wget https://github.com/nenadmarkus/pico/raw/c506795063f96ffeb4f9cce5d78a2c4b57fb88b0/rnt/cascades/facefinder
#
#
cat facefinder | hexdump -v -e '16/1 "0x%x," "\n"' > facefinder.hex
#
# you need to `source ./emsdk_env.sh` befor executing the following line
emcc main.c -o wasmpico.js -O3 -s EXPORTED_FUNCTIONS="['_find_faces', '_cluster_detections', '_malloc', '_free']" -s WASM=1
#
#
rm facefinder
rm facefinder.hex
rm picornt.c