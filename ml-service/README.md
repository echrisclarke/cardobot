# Card-o-Bot ML sidecar

FastAPI + PyTorch (`sentence-transformers`) service for:

- `/health`
- `/embed`
- `/index_card`
- `/similar`
- `/safety_check`

Set `ML_SERVICE_TOKEN` to match the PHP app. Mount `/data/ml` for model cache + embedding store.

```bash
docker build -t cardobot-ml .
docker run --rm -p 8000:8000 -e ML_SERVICE_TOKEN=dev -v mldata:/data/ml cardobot-ml
```
