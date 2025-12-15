// Run galleries
for (let i = 0; i < containers; i++) {
    console.log(images[i]);
    new Lumosaic('lumosaic-' + i, images[i]).init().then(() => {
        // Run lightbox
        new Obsidium('#lumosaic-' + i).init({
            counter: false,
            thumbnails: false
        })
    })
}