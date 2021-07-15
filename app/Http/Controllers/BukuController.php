<?php

namespace App\Http\Controllers;

use App\Buku;
use Illuminate\Http\Request;
use Illuminate\Contracts\Flysystem\FileNotFoundException;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Html\Builder as YajraBuilder;
use Illuminate\Support\Facades\File;
use Alert;
use PDF;

class BukuController extends Controller
{
    public function index(Request $request, YajraBuilder $builder)
    {
        if($request->ajax()){
            $buku = Buku::all();
            return DataTables::of($buku)
            ->editColumn('action', function($buku){
                return view('partials._action', [
                    'model'     => $buku,
                    'form_url'  => route('buku.destroy', $buku->id),
                    'edit_url'  => route('buku.edit', $buku->id),
                    'confirm_message'   => 'Yakin Mau Menghapus Data Ini?'

                ]);
            })
            ->editColumn('judul', function($buku){
                return view('partials._detail',[
                    'model'         => $buku,
                    'detail_url'   => route('buku.show', $buku->id)
                ]);
            })
            ->rawColumns(['action', 'judul'])
            ->make(true);
        }

        $html = $builder->columns([
            ['data' => 'judul', 'name' => 'judul', 'title' => 'Judul Buku'],
            ['data' => 'isbn', 'name' => 'isbn', 'title' => 'ISBN', 'class' => 'text-center'],
            ['data' => 'penerbit', 'name' => 'penerbit', 'title' => 'Penerbit'],
            ['data' => 'tahun_terbit', 'name' => 'tahun_terbit', 'title' => 'Tahun Terbit'],
            ['data' => 'jumlah', 'name' => 'jumlah', 'title' => 'Jumlah'],
            ['data' => 'deskripsi', 'name' => 'deskripsi', 'title' => 'Deskripsi'],
            ['data' => 'lokasi', 'name' => 'lokasi', 'title' => 'Lokasi'],
            ['data' => 'action', 'name' => 'action', 'title' => 'Aksi', 'orderable' => false, 'searchable' => false],
        ]);

        return view('buku.index', compact('html'));

    }

    public function create()
    {
        return view('buku.create');
    }

    public function store(Request $request)
    {
        $this->validate($request,
        [
            'judul'     => 'required',
            'isbn'      => 'required|numeric',
            'cover'     => 'required|mimes:jpg,png,jpeg|max:2048'
        ],
        [
            'isbn.required'     => 'Nomor ISBN Harus Diisi',
            'isbn.numeric'      => 'Nomor Harus Integer Bukan String',
            'cover.required'    => 'File Harus Diisi',
            'cover.mimes'       => 'File Cover Harus Betipe .png .jpg dan .jpeg',
            'cover.max'         => 'Maksimal File 1Mb'
        ]
        );

        $namaCover = NULL;

        if($request->hasFile('cover')){
            $fileupload     = $request->file('cover');
            $extension      = $fileupload->getClientOriginalExtension();
            $namaCover      = md5(time()).'.'.$extension;
            $destination    = public_path().DIRECTORY_SEPARATOR.'cover';
            $fileupload->move($destination, $namaCover);
        }

        $buku = Buku::create([
            'judul'         => $request->judul,
            'isbn'          => $request->isbn,
            'penerbit'      => $request->penerbit,
            'tahun_terbit'  => $request->tahun_terbit,
            'jumlah'        => $request->jumlah,
            'deskripsi'     => $request->deskripsi,
            'lokasi'        => $request->lokasi,
            'cover'         => $namaCover
        ]);

        toast('Berhasil Menambahkan Buku Baru', 'info');
        return redirect()->route('buku.index');
    }

    public function show(Buku $buku)
    {
        return view('buku.show', compact('buku'));
    }

    public function edit(Buku $buku)
    {
        return view('buku.edit', compact('buku'));
    }

    public function update(Request $request, Buku $buku)
    {
        $this->validate($request,
        [
            'judul'     => 'required',
            'isbn'      => 'required|numeric',
            'cover'     => 'nullable|mimes:jpg,png,jpeg|max:2048'
        ],
        [
            'isbn.required'     => 'Nomor ISBN Harus Diisi',
            'isbn.numeric'      => 'Nomor Harus Integer Bukan String',
            'cover.required'    => 'File Harus Diisi',
            'cover.mimes'       => 'File Cover Harus Betipe .png .jpg dan .jpeg',
            'cover.max'         => 'Maksimal File 1Mb'
        ]
        );

        $namaCover = NULL;

        if($request->hasFile('cover')){
            $fileupload     = $request->file('cover');
            $extension      = $fileupload->getClientOriginalExtension();
            $namaCover      = md5(time()).'.'.$extension;
            $destination    = public_path().DIRECTORY_SEPARATOR.'cover';

            if($buku->cover !== '')
            {
                $gambarLama = $buku->cover;
                $filePath   = public_path().DIRECTORY_SEPARATOR.'cover'.DIRECTORY_SEPARATOR. $gambarLama;
                try{
                    File::delete($filePath);
                }catch(FileNotFoundException $e)
                {
                    dd($e);
                }
            }
            $fileupload->move($destination, $namaCover);
        }

            $buku->update([
                'judul'         => $request->judul,
                'isbn'          => $request->isbn,
                'penerbit'      => $request->penerbit,
                'tahun_terbit'  => $request->tahun_terbit,
                'jumlah'        => $request->jumlah,
                'deskripsi'     => $request->deskripsi,
                'lokasi'        => $request->lokasi,
                'cover'         => $namaCover
            ]);

            toast('Berhasil Mengubah Data Buku', 'info');
            return redirect()->route('buku.index');
    }


    public function destroy(Buku $buku)
    {
        if ($buku->cover !== ''){
            $gambarLama = $buku->cover;
            $filePath   = public_path().DIRECTORY_SEPARATOR.'cover'.DIRECTORY_SEPARATOR. $gambarLama;
            $buku->destroy($buku->id);
            Alert::success('Berhasil', 'Hapus Sukeses');
            return redirect()->route('buku.index');
        }

    }

    public function cetakBuku()
    {
        $buku = Buku::all();
        $pdf = PDF::loadView('buku.cetak', compact('buku'))->setPaper('a4', 'landscape');
        return $pdf->stream('cetak buku.pdf');
    }
}
