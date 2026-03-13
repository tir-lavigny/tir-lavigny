import { mount } from '@vue/test-utils';
import L from 'leaflet';
import { describe, expect, it, vi } from 'vitest';
import MapIframe from './MapIframe.vue';

describe('MapIframe.vue', () => {
    it('renders correctly with given dimensions', () => {
        const wrapper = mount(MapIframe, {
            props: {
                latitude: 46.5,
                longitude: 6.5,
                height: '500px',
                width: '100%',
            },
        });

        expect(wrapper.element.style.height).toBe('500px');
        expect(wrapper.element.style.width).toBe('100%');
    });

    it('initializes Leaflet map on mount', () => {
        mount(MapIframe, {
            props: {
                latitude: 46.5,
                longitude: 6.5,
            },
        });

        expect(L.map).toHaveBeenCalled();
        expect(L.tileLayer).toHaveBeenCalled();
        expect(L.marker).toHaveBeenCalledWith([46.5, 6.5], expect.any(Object));
    });

    it('updates marker and view when props change', async () => {
        const wrapper = mount(MapIframe, {
            props: {
                latitude: 46.5,
                longitude: 6.5,
            },
        });

        await wrapper.setProps({ latitude: 47.0, longitude: 7.0 });

        expect(L.marker).toHaveBeenLastCalledWith([47.0, 7.0], expect.any(Object));
    });

    it('opens popup if title is provided', () => {
        const markerMock = {
            addTo: vi.fn().mockReturnThis(),
            bindPopup: vi.fn().mockReturnThis(),
            openPopup: vi.fn().mockReturnThis(),
        };
        (L.marker as any).mockReturnValue(markerMock);

        mount(MapIframe, {
            props: {
                latitude: 46.5,
                longitude: 6.5,
                title: 'My Location',
            },
        });

        expect(markerMock.bindPopup).toHaveBeenCalled();
        expect(markerMock.openPopup).toHaveBeenCalled();
    });
});
