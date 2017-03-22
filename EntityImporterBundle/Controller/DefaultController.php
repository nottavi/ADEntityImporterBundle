<?php

namespace AD\EntityImporterBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use \SplFileObject;
use Ddeboer\DataImport\Reader\CsvReader;
use Ddeboer\DataImport\Writer\DoctrineWriter;
use Ddeboer\DataImport\Workflow\StepAggregator;
use Doctrine\ORM\Mapping\ClassMetadata;

class DefaultController extends Controller
{
    public function importAction(Request $request, $entityClass)
    {

        if($request->get('import', false)){

            $uploadedFile = $uploadedFile=$request->files->get("csvFile");

            if (!$this->checkUploadedFileType($uploadedFile)){
                $request->getSession()->getFlashBag()->add('error', 'The file must be a CSV file');
            }else{
                $fileName = $this->moveUploadedFile($uploadedFile);

                echo "Starting to Import ".$fileName."<br />n";
                $this->importCSV($entityClass, $fileName);

                $request->getSession()->getFlashBag()->add('success', 'The import was successful');
            }

        }

        $templateVars['admin_pool'] = $this->container->get('sonata.admin.pool');
        $templateVars['entity_class'] = $entityClass;

        return $this->render('EntityImporterBundle:Default:import.html.twig', $templateVars);
    }

    public function checkUploadedFileType($uploadedFile){
        return $uploadedFile!=null && $uploadedFile->getMimeType() == 'text/plain';
    }

    public function moveUploadedFile($uploadedFile, $path = null, $fileName = null){
        if(!$path){
            $path=getcwd()."/dummyImport";
        }
        if(!$fileName){
            $fileName="importFile.csv";
        }
        $dest=$path."/".$fileName;
        @mkdir($path);
        @unlink($dest);

        // move file to dummy filename
        if($uploadedFile->move($path,$fileName)){
            return $dest;
        }
        return false;
    }

    public function importCSV($entityClass, $fileName, $steps = array()){
        $file = new SplFileObject($fileName);
        // Create and configure the reader
        $csvReader = new CsvReader($file,",");
        if ($csvReader===false) die("Can't create csvReader $fileName");
        $csvReader->setHeaderRowNumber(0);

        // this must be done to import CSVs where one of the data-field has CRs within!
        $file->setFlags(SplFileObject::READ_CSV |
            SplFileObject::SKIP_EMPTY |
            SplFileObject::READ_AHEAD);

        $em=$this->getDoctrine()->getManager();

        // Create the workflow
        $workflow = new StepAggregator($csvReader);
        if ($workflow===false) die("Can't create workflow $fileName");
        $curEntityClass= $entityClass;
        $writer = new DoctrineWriter($em, $entityClass);
        $writer->setTruncate(false);

        $entityMetadata=$em->getClassMetadata($entityClass);
        $entityMetadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);

        $workflow->addWriter($writer);

        foreach ($steps as $step) {
            $workflow->addStep($step);
        }

        $workflow->process();
    }
}
